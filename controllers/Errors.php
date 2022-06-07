<?php

namespace OnePilot\Client\Controllers;

use Illuminate\Http\Request;
use OnePilot\Client\Classes\LogsBrowser;

class Errors
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function browse(Request $request)
    {
        $browser = new LogsBrowser;
        $browser->setPagination($request->get('page', 1), $request->get('per_page', 50));
        $browser->setRange($request->get('from'), $request->get('to'));
        $browser->setSearch($request->get('search'));
        $browser->setLevels($request->get('levels'));

        return ['data' => $browser->get(), 'base_path' => base_path()] + $browser->getPagination();
    }
}
