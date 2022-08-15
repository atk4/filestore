<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;

class File extends Model
{
    public $table = 'filestore_file';

    public ?string $titleField = 'meta_filename';

    /** @var Filesystem */
    public $flysystem;

    public function newFile(): Model
    {
        $entity = $this->createEntity();

        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url');
        $this->addField('storage');
        $this->hasOne('source_file_id', [
            'model' => [self::class],
        ]);

        $this->addField(
            'status',
            ['enum' => ['draft', 'uploaded', 'thumbok', 'normalok', 'ready', 'linked'], 'default' => 'draft']
        );

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        $this->onHook(Model::HOOK_BEFORE_DELETE, function (self $m) {
            if ($m->flysystem) { // @phpstan-ignore-line
                $m->flysystem->delete($m->get('location'));
            }
        });
    }
}
