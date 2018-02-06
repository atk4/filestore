<?php
namespace atk4\filestore\FormField;

class Upload extends \atk4\ui\FormField\Upload 
{

    public $model = null; // File model

    function init() {
        parent::init();

        $this->onUpload([$this, 'uploaded']);
        $this->onDelete([$this, 'deleted']);

    }

    public function renderView()
    {
        if ($this->field->fieldFilename) {
           $this->set($this->field->get(), $this->field->fieldFilename->get());
        }
        return parent::renderView();
    }

    public function uploaded($file)
    {
        // provision a new file for specified flysystem
        $f = $this->field->model;
        $f->newFile($this->field->flysystem);

        // add (or upload) the file
        $stream = fopen($file['tmp_name'], 'r+');
        $this->field->flysystem->writeStream($f['location'], $stream, ['visibility'=>'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // get meta from browser
        $f['meta_mime_type'] = $file['type'];

        // store meta-information
        $is = getimagesize($file['tmp_name']);
        if($f['meta_is_image'] = (boolean)$is){
            $f['meta_mime_type'] = $is['mime'];
            $f['meta_image_width'] = $is[0];
            $f['meta_image_height'] = $is[1];
            //$m['extension'] = $is['mime'];
        }
        $f['meta_md5'] = md5_file($file['tmp_name']);
        $f['meta_filename'] = $file['name'];
        $f['meta_size'] = $file['size'];


        $f->save();
        $this->setFileId($f['token']);
    }

    public  function deleted($token)
    {
        return new \atk4\ui\jsNotify(['content' => $token.' has been removed!', 'color' => 'green']);
    }
}

