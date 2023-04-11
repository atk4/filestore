<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;

class File extends Model
{
    public $table = 'filestore_file';

    public ?string $titleField = 'meta_filename';

    /** @const string All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** @const string Not implemented */
    //public const STATUS_UPLOADED = 'uploaded';
    /** @const string Not implemented */
    //public const STATUS_THUMB_OK = 'thumbok';
    /** @const string Not implemented */
    //public const STATUS_NORMAL_OK = 'normalok';
    /** @const string Not implemented */
    //public const STATUS_READY = 'ready';
    /** @const string When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const array */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        //self::STATUS_UPLOADED,
        //self::STATUS_THUMB_OK,
        //self::STATUS_NORMAL_OK,
        //self::STATUS_READY,
        self::STATUS_LINKED,
    ];

    /** @int Delay in seconds used to avoid race condition when cleaning updraft records */
    protected int $cleanupDraftsDelay = 15;

    /** @var Filesystem */
    public $flysystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url');
        $this->addField('storage'); // not implemented
        $this->hasOne('source_file_id', [ // this field can be used to link thumb images (when we'll implement that) to source image for example
            'model' => [self::class],
        ]);

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

        // cascade-delete all related child files
        $this->onHook(Model::HOOK_BEFORE_DELETE, function (self $m) {
            $files = (clone $this->getModel())
                ->addCondition('source_file_id', $m->getId());
            foreach ($files as $file) {
                $file->delete();
            }
        });

        // delete physical file from storage after we delete DB record
        $this->onHook(Model::HOOK_AFTER_DELETE, function (self $m) {
            $path = $m->get('location');
            if ($path && $m->flysystem && $m->flysystem->fileExists($path)) { // @phpstan-ignore-line
                $m->flysystem->delete($path);
            }
        });
    }

    public function newFile(): Model
    {
        $entity = $this->createEntity();
        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }

    /**
     * Useful method to clean up all draft files.
     * Can be called as user action or on schedule bases to clean up filestore repository.
     *
     * @return $this
     */
    public function cleanupDrafts()
    {
        $files = (clone $this->getModel())
            ->addCondition('status', self::STATUS_DRAFT)
            ->addCondition('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->cleanupDraftsDelay . 'S')))
            ;
        foreach ($files as $file) {
            $file->delete();
        }

        return $this;
    }
}
