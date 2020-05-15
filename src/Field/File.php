<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\filestore\Field;

class File extends \atk4\data\Field_SQL
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }


    public $ui = ['form'=>'\atk4\filestore\FormField\Upload'];

    /**
     * Set a custom model for File
     */
    public $model = null;

    /**
     * Will contain path of the file while it's stored locally
     *
     * @var string
     */
    public $localField = null;

    public $flysystem = null;

    public $normalizedField = null;

    public $reference;

    public $fieldFilename;
    public $fieldURL;

    public function init(): void
    {
        $this->_init();

        if (!$this->model) {
            $this->model = new \atk4\filestore\Model\File($this->owner->persistence);
            $this->model->flysystem = $this->flysystem;
        }

        $this->normalizedField = preg_replace('/_id$/', '', $this->short_name);
        $this->reference = $this->owner->hasOne($this->short_name, [$this->model, 'their_field'=>'token']);

        $this->importFields();

        $this->owner->addHook('beforeSave', function($m) {
            if ($m->isDirty($this->short_name)) {
                $old = $m->dirty[$this->short_name];
                $new = $m[$this->short_name];

                // remove old file, we don't need it
                if($old) {
                    $m->refModel($this->short_name)->loadBy('token', $old)->delete();
                }

                // mark new file as linked
                if($new) {
                    $m->refModel($this->short_name)->loadBy('token', $new)->save(['status'=>'linked']);
                }
            }
        });
        $this->owner->addHook('beforeDelete', function($m) {
            $token = $m[$this->short_name];
            if ($token) {
                $m->refModel($this->short_name)->loadBy('token', $token)->delete();
            }
        });
    }

    function importFields()
    {
        //$this->reference->addField($this->normalizedField.'_token', 'token');
        $this->fieldURL = $this->reference->addField($this->normalizedField.'_url', 'url');
        $this->fieldFilename = $this->reference->addField($this->normalizedField.'_filename', 'meta_filename');
    }




    function __construct(\League\Flysystem\Filesystem $flysystem) {
        $this->flysystem = $flysystem;
    }

    public function normalize($value)
    {
        return parent::normalize($value);
    }

    /**
     * DO NOT CALL THIS METHOD. It is automatically invoked when you save
     * your model.
     *
     * When storing password to persistance, it will be encrypted. We will
     * also update $this->password_hash, in case you'll want to perform
     * verify right after.
     *
     * @param string $password plaintext password
     *
     * @return string encrypted password
     */
    /*
    public function encrypt($password)
    {
        if (is_null($password)) {
            return;
        }

        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->password_hash;
    }
     */

    /**
     * Verify if the password user have suppplied you with is correct.
     *
     * @param string $password plain text password
     *
     * @return bool true if passwords match
     */
    public function compare($password): bool
    {
        if (is_null($this->password_hash)) {

            // perhaps we currently hold a password and it's not saved yet.
            $v = $this->get();

            if ($v) {
                return $v === $password;
            }

            throw new \atk4\data\Exception(['Password was not set, so verification is not possible', 'field'=>$this->name]);
        }

        return password_verify($password, $this->password_hash);
    }

    /**
     * Randomly generate a password, that is easy to memorize. There are
     * 116985856 unique password combinations with length of 4.
     *
     * To make this more complex, use suggestPasssword(3).' '.suggestPassword(3);
     *
     * @return string
     */
    public function suggestPassword($length = 4, $words = 1)
    {
        $p5 = ['','k','s','t','n','h','m','r','w','g','z','d','b','p'];
        $p3 = ['y','ky','sh','ch','ny','my','ry','gy','j','py','by'];
        $a5 = ['a','i','u','e','o'];
        $a3 = ['a','u','o'];
        $syl=['n'];

        foreach($p5 as $p) {
            foreach($a5 as $a) {
                $syl[] = $p.$a;
            }
        }

        foreach($p3 as $p) {
            foreach($a3 as $a) {
                $syl[] = $p.$a;
            }
        }

        $pass = '';

        for ($i=0; $i<$length; $i++) {
            $pass.=$syl[array_rand($syl)];
        }

        return $pass;
    }
}
