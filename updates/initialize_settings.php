<?php namespace OnePilot\Client\Updates;

use OnePilot\Client\Models\Settings;
use October\Rain\Database\Updates\Migration;

class InitializeKey extends Migration
{
    public function up()
    {
        if (!Settings::instance()->private_key) {
            Settings::set('private_key', str_random(64));
        }
    }

    public function down()
    {
        Settings::set('private_key', null);
    }
}
