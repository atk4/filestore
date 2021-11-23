<?php

declare(strict_types=1);

namespace Atk4\Filestore\Form\Control;

use Atk4\Filestore\Field\FileField;

class Upload extends \Atk4\Ui\Form\Control\Upload
{
    /**
     * @var FileField
     */
    public $field;

    /**
     * @var \Atk4\Filestore\Model\File
     */
    public $model; // File model

    protected function init(): void
    {
        parent::init();

        $this->onUpload(\Closure::fromCallable([$this, 'uploaded']));
        $this->onDelete(\Closure::fromCallable([$this, 'deleted']));
    }

    protected function uploaded($file)
    {
        // provision a new file for specified flysystem
        $f = $this->field->model;
        $f->newFile($this->field->flysystem);

        // add (or upload) the file
        $stream = fopen($file['tmp_name'], 'r+');
        $this->field->flysystem->writeStream($f->get('location'), $stream, ['visibility' => 'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $detector = new \League\MimeTypeDetection\FinfoMimeTypeDetector();

        $mimeType = $detector->detectMimeTypeFromFile($file['tmp_name']);
        // get meta from browser
        $f->set('meta_mime_type', $mimeType);

        // store meta-information
        $is = getimagesize($file['tmp_name']);
        if ($f->set('meta_is_image', (bool) $is) === true) {
            $f->set('meta_image_width', $is[0]);
            $f->set('meta_image_height', $is[1]);
        }

        $f->set('meta_md5', md5_file($file['tmp_name']));
        $f->set('meta_filename', $file['name']);
        $f->set('meta_size', $file['size']);

        $f->save();
        $this->setFileId($f->get('token'));
    }

    protected function deleted($token)
    {
        $f = $this->field->model;
        $f->tryLoadBy('token', $token);

        $js = new \Atk4\Ui\JsNotify(['content' => $f->get('meta_filename') . ' has been removed!', 'color' => 'green']);
        if ($f->get('status') === 'draft') {
            $f->delete();
        }

        return $js;
    }

    protected function renderView(): void
    {
        if ($this->field->fieldFilename) {
            $this->set($this->field->get($this->entityField->getEntity()), $this->field->fieldFilename->get($this->entityField->getEntity()));
        }
        parent::renderView();
    }
}
