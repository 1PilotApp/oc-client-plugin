<?php namespace OnePilot\Client\Models;

use Cache;
use Carbon\Carbon;
use Model;

/**
 * @method static self instance()
 * @mixin \System\Behaviors\SettingsModel
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = '1Pilot_client_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';

    public function logCronExecution()
    {
        $this->setSettingsValue('last_cron_at', Carbon::now()->timestamp);
        $this->unbindEvent('model.afterSave');
        $this->save();

        Cache::forget('system::settings.' . $this->settingsCode);
    }
}
