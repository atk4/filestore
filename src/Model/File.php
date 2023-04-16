<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

class File extends Model
{
    public $table = 'filestore_file';

    public ?string $titleField = 'meta_filename';

    /** All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const list<string> */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINKED,
    ];

    /** @var Filesystem */
    public $flysystem;

    /** @int Delay in seconds used to avoid race condition when cleaning up draft records */
    protected int $cleanupDraftsDelay = 60;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url');

        $this->addField('status', [
            'enum' => self::ALL_STATUSES,
            'default' => self::STATUS_DRAFT,
        ]);
        $this->addField('created_at', ['type' => 'datetime', 'required' => true]);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        $this->onHook(Model::HOOK_BEFORE_SAVE, function (self $m, bool $isUpdate) {
            if ($isUpdate === false) {
                $m->set('created_at', new \DateTime());
            }
        });

        // delete physical file from storage after we delete DB record
        $this->onHook(Model::HOOK_AFTER_DELETE, function (self $m) {
            $path = $m->get('location');
            if ($path && $m->flysystem->fileExists($path)) {
                $m->flysystem->delete($path);
            }
        });
    }

    /**
     * @return static
     */
    public function newFile(): Model
    {
        $this->assertIsModel();

        $entity = $this->createEntity();
        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }

    /**
     * Create new entity from file path.
     *
     * @param string $path     Path to file to import
     * @param string $fileName Optional original file name
     *
     * @return static
     */
    public function createFromPath(string $path, string $fileName = null): Model
    {
        $this->assertIsModel();

        if ($fileName === null) {
            $fileName = basename($path);
        }

        $entity = $this->newFile();

        // store file in filesystem
        $stream = fopen($path, 'r+');
        $entity->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        fclose($stream);

        // detect mime-type
        $detector = new FinfoMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromFile($path);
        $entity->set('meta_mime_type', $mimeType);

        // store meta-information
        $entity->set('meta_md5', md5_file($path));
        $entity->set('meta_filename', $fileName);
        $entity->set('meta_size', filesize($path));
        $pos = strrpos($fileName, '.');
        if ($pos !== false) {
            $ext = strtolower(substr($fileName, $pos + 1));
            if ($ext !== 'tmp') {
                $entity->set('meta_extension', $ext);
            }
        }

        // additional meta-information for images
        $imageSizeArr = getimagesize($path);
        $entity->set('meta_is_image', $imageSizeArr !== false);
        if ($imageSizeArr !== false) {
            $entity->set('meta_image_width', $imageSizeArr[0]);
            $entity->set('meta_image_height', $imageSizeArr[1]);
        }

        $entity->save();

        return $entity;
    }

    /**
     * Useful method to clean up all draft files.
     * Can be called as user action or on schedule bases to clean up filestore repository.
     */
    public function cleanupDrafts(): void
    {
        $m = $this->isEntity() ? $this->getModel() : $this;

        $files = (clone $m)
            ->addCondition('status', self::STATUS_DRAFT)
            ->addCondition('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->cleanupDraftsDelay . 'S')));
        foreach ($files as $file) {
            $file->delete();
        }
    }
}
