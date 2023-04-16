<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;

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

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

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
}
