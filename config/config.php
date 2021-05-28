<?php

return [
    /*
     * Block remote install from 1Pilot
     */
    'disable-plugin-install' => env('1PILOT_READONLY', false) ?: env('1PILOT_DISABLE_PLUGIN_INSTALL', false),

    /*
     * Block plugin update from 1Pilot
     */
    'disable-plugin-update' => env('1PILOT_READONLY', false) ?: env('1PILOT_DISABLE_PLUGIN_UPDATE', false),

    /*
     * Block core update from 1Pilot
     */
    'disable-core-update' => env('1PILOT_READONLY', false) ?: env('1PILOT_DISABLE_CORE_UPDATE', false),
];
