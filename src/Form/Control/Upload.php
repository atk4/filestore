<?php

declare(strict_types=1);

namespace Atk4\Filestore\Form\Control;

use Atk4\Data\Model;
use Atk4\Data\Model\EntityFieldPair;
use Atk4\Filestore\Field\FileField;
use Atk4\Ui\JsExpressionable;

/**
 * @phpstan-property EntityFieldPair<Model, FileField> $entityField
 */
class Upload extends \Atk4\Ui\Form\Control\Upload
{
    protected function init(): void
    {
        parent::init();

        $this->onUpload(\Closure::fromCallable([$this, 'uploaded']));
        $this->onDelete(\Closure::fromCallable([$this, 'deleted']));
    }

    protected function uploaded(array $file): ?JsExpressionable
    {
        // provision a new file for specified flysystem
        $model = $this->entityField->getField()->fileModel;
        $entity = $model->newFile();

        // add (or upload) the file
        $stream = fopen($file['tmp_name'], 'r+');
        $this->entityField->getField()->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $detector = new \League\MimeTypeDetection\FinfoMimeTypeDetector();

        $mimeType = $detector->detectMimeTypeFromFile($file['tmp_name']);
        // get meta from browser
        $entity->set('meta_mime_type', $mimeType);

        // store meta-information
        $imageSizeArr = getimagesize($file['tmp_name']);
        $entity->set('meta_is_image', $imageSizeArr !== false);
        if ($imageSizeArr !== false) {
            $entity->set('meta_image_width', $imageSizeArr[0]);
            $entity->set('meta_image_height', $imageSizeArr[1]);
        }

        $entity->set('meta_md5', md5_file($file['tmp_name']));
        $entity->set('meta_filename', $file['name']);
        $entity->set('meta_size', $file['size']);

        $entity->save();
        $this->setFileId($entity->get('token'));

        return null;
    }

    protected function deleted(string $token): ?JsExpressionable
    {
        $model = $this->entityField->getField()->fileModel;
        $entity = $model->loadBy('token', $token);

        if ($entity->get('status') === 'draft') {
            $entity->delete();
        }

        return null;
    }

    protected function renderView(): void
    {
        if ($this->entityField->getField()->fieldFilename) { // @phpstan-ignore-line
            $this->set(
                $this->entityField->get(),
                $this->entityField->getField()->fieldFilename->get($this->entityField->getEntity())
            );
        }
        parent::renderView();
    }
}
