<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $client = new Client();

        $file = fopen($request->file('file')->getRealPath(), 'r');

        $response = $client->post(env('MFILES_BASE_URL')."/objects/0/0/files", [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $file,
                    'filename' => $request->file('file')->getClientOriginalName()
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
}
