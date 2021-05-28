<?php namespace OnePilot\Client\Updates;

use CmsPilot\Client\Models\Settings as CmsPilotSettings;
use Illuminate\Support\Facades\File;
use October\Rain\Database\Updates\Migration;
use OnePilot\Client\Models\Settings;
use System\Classes\PluginManager;

class MigrateFromCmspilot extends Migration
{
    public function up()
    {
        if (!$this->cmsPilotIsInstalled()) {
            return;
        }

        if (!empty($privateKey = CmsPilotSettings::get('private_key'))) {
            Settings::set('private_key', $privateKey);
        }

        PluginManager::instance()->deletePlugin('CmsPilot.Client');
    }

    public function down()
    {
    }

    private function cmsPilotIsInstalled()
    {
        return File::exists(plugins_path('cmspilot/client/Plugin.php'));
    }
}
