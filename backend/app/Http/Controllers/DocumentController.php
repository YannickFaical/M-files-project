<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Services\MFilesService;

class DocumentController extends Controller
{
    private $mfilesService;

    public function __construct(MFilesService $mfilesService)
    {
        $this->mfilesService = $mfilesService;
    }

    public function index()
    {
        try {
            $data = $this->mfilesService->getDocuments();
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

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
