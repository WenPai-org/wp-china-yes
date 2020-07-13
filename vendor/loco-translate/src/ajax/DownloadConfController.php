<?php
/**
 * Downloads a bundle configuration as XML or Json
 */
class Loco_ajax_DownloadConfController extends Loco_ajax_common_BundleController {
    
    
    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $this->validate();
        $bundle = $this->getBundle();

        $file = new Loco_fs_File( $this->get('path') );
        // TODO should we download axtual loco.xml file if bundle is configured from it?
        //$file->normalize( $bundle->getDirectoryPath() );
        //if( $file->exists() ){}
        
        $writer = new Loco_config_BundleWriter($bundle);
        
        switch( $file->extension() ){
        case 'xml':
            return $writer->toXml();
        case 'json':
            return json_encode( $writer->jsonSerialize() );
        }
        
        // @codeCoverageIgnoreStart
        throw new Loco_error_Exception('Specify either XML or JSON file path');
    }
        
    
}