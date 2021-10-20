<?php

declare(strict_types=1);

namespace Atk4\Filestore\Form\Control;

use Atk4\Filestore\Field\File;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

class Upload extends \Atk4\Ui\Form\Control\Upload
{
    /**
     * @var File
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
        $entity = $f->newFile($this->field->flysystem);

        // add (or upload) the file
        $stream = fopen($file['tmp_name'], 'r+');
        $this->field->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $detector = new \League\MimeTypeDetection\FinfoMimeTypeDetector();

        $mimeType = $detector->detectMimeTypeFromFile($file['tmp_name']);
        // get meta from browser
        $entity->set('meta_mime_type', $mimeType);

        // store meta-information
        $is = getimagesize($file['tmp_name']);
        if ($entity->set('meta_is_image', (bool) $is) === true) {
            $entity->set('meta_image_width', $is[0]);
            $entity->set('meta_image_height', $is[1]);
        }

        $entity->set('meta_md5', md5_file($file['tmp_name']));
        $entity->set('meta_filename', $file['name']);
        $entity->set('meta_size', $file['size']);

        $entity->save();
        $this->setFileId($entity->get('token'));
    }

    protected function deleted($token)
    {
        $f = $this->field->model;
        $entity = $f->tryLoadBy('token', $token);

        $js = new \Atk4\Ui\JsNotify(['content' => $entity->get('meta_filename') . ' has been removed!', 'color' => 'green']);
        if ($entity->get('status') === 'draft') {
            $entity->delete();
        }

        return $js;
    }

    protected function renderView(): void
    {
        if ($this->field->fieldFilename->field) {
            $this->set($this->field->get(), $this->field->fieldFilename->get());
        }
        parent::renderView();
    }
}
