<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ClientController extends Controller
{
    public function index()
    {
        $client = new Client();
        $response = $client->get(env('MFILES_BASE_URL')."/objects/0/0/latest");
        return json_decode($response->getBody(), true);
    }

    public function store(Request $request)
    {
        $client = new Client();
        $response = $client->post(env('MFILES_BASE_URL')."/objects/0", [
            'json' => [
                'PropertyValues' => [
                    [
                        'PropertyDef' => 0, // Name or Title
                        'TypedValue' => ['DataType' => 1, 'Value' => $request->name]
                    ]
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
}
