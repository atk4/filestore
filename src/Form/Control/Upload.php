<?php
namespace atk4\filestore\Form\Control;

class Upload extends \atk4\ui\Form\Control\Upload 
{

    public $model = null; // File model

    function init(): void {
        parent::init();

        $this->onUpload(function ($files) {
            $this->uploaded($file); 
        });
        $this->onDelete(function ($token) {
            $this->deleted($token);
        });

    }

    protected function renderView(): void
    {
        if ($this->field->fieldFilename) {
           $this->set($this->field->get(), $this->field->fieldFilename->get());
        }
        parent::renderView();
    }

    public function uploaded($file)
    {
       
        // provision a new file for specified flysystem
        $f = $this->field->model;
        $f->newFile($this->field->flysystem);

        // add (or upload) the file
        $stream = fopen($file['tmp_name'], 'r+');
        $this->field->flysystem->writeStream($f->get('location'), $stream, ['visibility'=>'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // get meta from browser
        $f->set('meta_mime_type', $file['type']);

        // store meta-information
        $is = getimagesize($file['tmp_name']);
        if($f->set('meta_is_image', (boolean)$is)){
            $f->set('meta_mime_type', $is['mime']);
            $f->set('meta_image_width', $is[0]);
            $f->set('meta_image_height', $is[1]);
            //$m['extension'] = $is['mime'];
        }
        $f->set('meta_md5', md5_file($file['tmp_name']));
        $f->set('meta_filename', $file['name']);
        $f->set('meta_size', $file['size']);


        $f->save();
        $this->setFileId($f->get('token'));
 
    }

    public  function deleted($token)
    {
        $f = $this->field->model;
        $f->tryLoadBy('token', $token);

        $js =  new \atk4\ui\JsNotify(['content' => $f->get('meta_filename').' has been removed!', 'color' => 'green']);
        if ($f->get('status') == 'draft') {
            $f->delete();
        }

        return $js;
    }
}

