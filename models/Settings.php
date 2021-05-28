<?php namespace OnePilot\Client\Models;

use Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = '1Pilot_client_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';
}