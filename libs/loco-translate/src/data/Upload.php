<?php
 /**
  * Simple wrapper for transient file uploads carrying PO data.
  * Doesn't move or persist uploaded files, so doesn't call wp_handle_upload()
  */
 class Loco_data_Upload {

     /**
      * @var Loco_fs_File
      */
     private $file;


     /**
      * Pass through temporary file data
      * @param string key in $_FILES known to exist
      * @return string
      * @throws Loco_error_UploadException
      */
     public static function src($key){
         $upload = new Loco_data_Upload($_FILES[$key]);
         return $upload->file->getContents();
     }
     

     /**
      * @param array member of $_FILE
      * @throws Loco_error_UploadException
      */
    public function __construct( array $data ){
        // https://www.php.net/manual/en/features.file-upload.errors.php
        $code = (int) $data['error'];
        switch( $code ){
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
            throw new Loco_error_UploadException('The uploaded file exceeds the upload_max_filesize directive in php.ini',$code);
        case UPLOAD_ERR_FORM_SIZE:
            throw new Loco_error_UploadException('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',$code);
        case UPLOAD_ERR_PARTIAL:
            throw new Loco_error_UploadException('The uploaded file was only partially uploaded',$code);
        case UPLOAD_ERR_NO_FILE:
            throw new Loco_error_UploadException('No file was uploaded, or data is empty',$code);
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new Loco_error_UploadException('Missing temporary folder for uploaded file',$code);
        case UPLOAD_ERR_CANT_WRITE:
            throw new Loco_error_UploadException('Failed to save uploaded file to disk',$code);
        case UPLOAD_ERR_EXTENSION:
            throw new Loco_error_UploadException('Your server blocked the file upload',$code);
        default:
            throw new Loco_error_UploadException('Unknown file upload error',$code);
        }
        // mime check is largely pointless but may as well check as we'll only send one type
        if( 'application/x-gettext' !== $data['type'] ){
            throw new Loco_error_UploadException('Unsupported file type, expected PO or POT file');
        }
        // upload is OK according to PHP, but check it's really readable and not empty
        $path = $data['tmp_name'];
        $file = new Loco_fs_File($path);
        if( ! $file->exists() ){
            throw new Loco_error_UploadException('Uploaded file is not readable',UPLOAD_ERR_NO_FILE);
        }
        if( 0 === $file->size() ){
            throw new Loco_error_UploadException('Uploaded file contains no data',UPLOAD_ERR_NO_FILE);
        }
        // file is really ok
        $this->file = $file;
    }
     
 }
 