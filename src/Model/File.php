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

    /** @const string All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** @const string When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const array */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINKED,
    ];

    /** @var Filesystem */
    public $flysystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url'); // not implemented
        $this->addField('storage'); // not implemented
        $this->hasOne('source_file_id', [ // this field can be used to link thumb images (when we'll implement that) to source image for example
            'model' => [self::class],
        ]);

        $this->addField('status', [
            'enum' => self::ALL_STATUSES,
            'default' => self::STATUS_DRAFT,
        ]);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        // cascade-delete all related child files
        $this->onHook(Model::HOOK_BEFORE_DELETE, function (self $m) {
            $files = (clone $m->getModel())->addCondition('source_file_id', $m->getId());
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
        $this->assertIsModel();

        $entity = $this->createEntity();
        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }
}
