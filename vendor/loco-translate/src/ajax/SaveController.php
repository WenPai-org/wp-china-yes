<?php
/**
 * Ajax "save" route, for saving editor contents to disk
 */
class Loco_ajax_SaveController extends Loco_ajax_common_BundleController {

    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $post = $this->validate();
        
        // path parameter must not be empty
        $path = $post->path;
        if( ! $path ){
            throw new InvalidArgumentException('Path parameter required');
        }
        
        // locale must be posted to indicate whether PO or POT
        $locale = $post->locale;
        if( is_null($locale) ){
            throw new InvalidArgumentException('Locale parameter required');
        }

        $pofile = new Loco_fs_LocaleFile( $path );
        $pofile->normalize( loco_constant('WP_CONTENT_DIR') );
        $source_pofile = Loco_gettext_Data::fromSource( $pofile->getContents() );
        $source_pofile_array = $source_pofile->jsonSerialize();
        $poexists = $pofile->exists();

        // ensure we only deal with PO/POT source files.
        // posting of MO file paths is permitted when PO is missing, but we're about to fix that
        $ext = $pofile->extension();
        if( 'mo' === $ext ){
            $pofile = $pofile->cloneExtension('po');
        }
        else if( 'pot' === $ext ){
            $locale = '';
        }
        else if( 'po' !== $ext ){
            throw new Loco_error_Exception('Invalid file path');
        }
        
        // force the use of remote file system when configured from front end
        $api = new Loco_api_WordPressFileSystem;

        // data posted may be either 'multipart/form-data' (recommended for large files)
        if( isset($_FILES['po']) ){
            $data = Loco_gettext_Data::fromSource( Loco_data_Upload::src('po') );
        }
        // else 'application/x-www-form-urlencoded' by default
        else {
            $data = Loco_gettext_Data::fromSource( $post->data );
        }
        
        // WordPress-ize some headers that differ from JavaScript libs
        if( $compile = (bool) $locale ){
            $head = $data->getHeaders();
            $head['Language'] = strtr( $locale, '-', '_' );
        }
        
        // backup existing file before overwriting, but still allow if backups fails
        $num_backups = Loco_data_Settings::get()->num_backups;
        if( $num_backups && $poexists ){
            try {
                $api->authorizeCopy( $pofile );
                $backups = new Loco_fs_Revisions( $pofile );
                $backups->create();
                $backups->prune($num_backups);
            }
            catch( Exception $e ){
                Loco_error_AdminNotices::debug( $e->getMessage() );
                $message = __('Failed to create backup file in "%s". Check file permissions or disable backups','loco-translate');
                Loco_error_AdminNotices::warn( sprintf( $message, $pofile->getParent()->basename() ) );
            }
        }

        // commit file directly to disk
        $api->authorizeSave( $pofile );
        $bytes = $pofile->putContents( $data->msgcat() );
        $mtime = $pofile->modified();

        // add bundle to recent items on file creation
        try {
            $bundle = $this->getBundle();
            Loco_data_RecentItems::get()->pushBundle( $bundle )->persist();
        }
        catch( Exception $e ){
            // editor permitted to save files not in a bundle, so catching failures
            $bundle = null;
        }
        
        // start success data with bytes written and timestamp
        $this->set('locale', $locale );
        $this->set('pobytes', $bytes );
        $this->set('poname', $pofile->basename() );
        $this->set('modified', $mtime);
        $this->set('datetime', Loco_mvc_ViewParams::date_i18n($mtime) );

        // Compile MO and JSON files unless saving template
        if( $compile ){
            try {
                $mofile = $pofile->cloneExtension('mo');
                $api->authorizeSave( $mofile );
                $bytes = $mofile->putContents( $data->msgfmt() );
                $this->set( 'mobytes', $bytes );

                /**
                 * 默认的通过PO文件内容生成的数组是个纯二维数组，查找效率很低，所以对数组结构进行调整，把源字符串提取出来作为一级数组的KEY
                 *
                 * @param $pofile_array  $pofile->jsonSerialize()方法生成的PO文件的格式化数组
                 *
                 * @return array
                 */
                $pofile_key_array = function($pofile_array) {
                    $pofile_key_array = [];
                    foreach ($pofile_array as $v) {
                        if (empty($v['source'])) {
                            continue;
                        }
                        $pofile_key_array[$v['source']] = $v;
                    }

                    return $pofile_key_array;
                };

                $source_pofile_key_array = $pofile_key_array($source_pofile_array);
                $pofile_key_array = $pofile_key_array($data->jsonSerialize());


                /**
                 * 取出用户提交的PO文件结构化数组相较于原版PO文件数组的差异部分
                 */
                $pofile_diff_array = [];
                foreach ($source_pofile_key_array as $k => $v) {
                    if (!array_key_exists($k, $pofile_key_array)) {
                        continue;
                    }

                    if ($v['target'] != $pofile_key_array[$k]['target']) {
                        $pofile_diff_array = array_merge($pofile_diff_array, [$pofile_key_array[$k]]);
                    }
                }

                /**
                 * 将差异部分上报给WP-China.org
                 */
                $form_data = array(
                    'data' => json_encode($pofile_diff_array, JSON_UNESCAPED_UNICODE)
                );

                $response = wp_safe_remote_request(sprintf('https://openapi.wp-china-yes.net/v1/translations/%s/%s',
                    $pofile->getParent()->basename(), str_replace('-zh_CN.po', '', $pofile->basename())),
                    ['method' => 'PUT', 'body' => $form_data]);

                $update_trans_ok = false;
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ($body['code'] == 0) {
                    $update_trans_ok = true;
                }

                if ($update_trans_ok) {
                    Loco_error_AdminNotices::success('翻译校准已在本地实时生效并已成功上传到WP-China.org');
                } else {
                    Loco_error_AdminNotices::success('翻译校准已在本地实时生效但未成功上传到WP-China.org，WP-China.org返回错误：'.$body['message']);
                }
            }
            catch( Exception $e ){
                Loco_error_AdminNotices::debug( $e->getMessage() );
                Loco_error_AdminNotices::warn( __('PO file saved, but MO file compilation failed','loco-translate') );
                $this->set( 'mobytes', 0 );
                // prevent further compilation if MO failed
                $compile = false;
            }
        }
        else {
            Loco_error_AdminNotices::success( __('POT file saved','loco-translate') );
        }

        /*/ Compile JSON translations for WordPress >= 5
        if( $compile && $bundle && function_exists('wp_set_script_translations') ){
            $bytes = 0;
            try {
                list($domain) = Loco_package_Project::splitId( $this->get('domain') );
                
                // hash file reference according to WordPress logic (see load_script_textdomain)
                $base = $pofile->dirname().'/'.$pofile->filename();
                foreach( $data->exportRefs('\\.jsx?') as $ref => $messages ){
                    if( '.min.js' === substr($ref,-7) ) {
                        $ref = substr($ref,0,-7).'.js';
                    }
                    // filter similarly to WP's `load_script_textdomain_relative_path` which is called from `load_script_textdomain`
                    $ref = apply_filters( 'loco_script_relative_path', $ref, $domain );
                    // referenced file must exist in bundle, or will never be loaded and so not require a .json file
                    $file = new Loco_fs_File( $bundle->getDirectoryPath().'/'.$ref );
                    if( $file->exists() && ! $file->isDirectory() ){
                        $file = new Loco_fs_File( $base.'-'.md5($ref).'.json' );
                        $api->authorizeSave( $file );
                        $bytes += $file->putContents( $data->jedize($domain,$messages) );
                    }
                    else {
                        Loco_error_AdminNotices::warn( sprintf('%s not found in bundle',$ref) );
                    }
                }

                // single JSON file containing all .js ref from this file
                if( $messages = $data->splitJs() ){
                    $file = $pofile->cloneExtension('json');
                    $api->authorizeSave( $file );
                    $bytes = $file->putContents( $data->jedize($domain,$messages) );
                }
            }
            catch( Exception $e ){
                Loco_error_AdminNotices::debug( $e->getMessage() );
                Loco_error_AdminNotices::warn( __('JSON compilation failed','loco-translate') );
            }
            $this->set( 'jsbytes', $bytes );
        }*/
        
        return parent::render();
    }
    
    
}