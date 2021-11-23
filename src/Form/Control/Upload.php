<?php

declare(strict_types=1);

namespace Atk4\Filestore\Form\Control;

use Atk4\Filestore\Field\FileField;
use Atk4\Filestore\Model\File;
use Atk4\Ui\JsExpressionable;

class Upload extends \Atk4\Ui\Form\Control\Upload
{
    /** @var FileField */
    public $field;

    /** @var File */
    public $model;

    protected function init(): void
    {
        parent::init();

        $this->onUpload(\Closure::fromCallable([$this, 'uploaded']));
        $this->onDelete(\Closure::fromCallable([$this, 'deleted']));
    }

    protected function uploaded(array $file): void
    {
        // provision a new file for specified flysystem
        $f = $this->field->model;
        $f->flysystem = $this->field->flysystem; // TODO not sure if needed
        $f->newFile();

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
        $imageSizeArr = getimagesize($file['tmp_name']);
        $f->set('meta_is_image', $imageSizeArr !== false);
        if ($imageSizeArr !== false) {
            $f->set('meta_image_width', $imageSizeArr[0]);
            $f->set('meta_image_height', $imageSizeArr[1]);
        }

        $f->set('meta_md5', md5_file($file['tmp_name']));
        $f->set('meta_filename', $file['name']);
        $f->set('meta_size', $file['size']);

        $f->save();
        $this->setFileId($f->get('token'));
    }

    protected function deleted(string $token): JsExpressionable
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
        if ($this->field->fieldFilename) { // @phpstan-ignore-line
            $this->set($this->field->get($this->entityField->getEntity()), $this->field->fieldFilename->get($this->entityField->getEntity()));
        }
        parent::renderView();
    }
}
