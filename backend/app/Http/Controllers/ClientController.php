<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\MFilesService;

class ClientController extends Controller
{
    private $mfilesService;

    public function __construct(MFilesService $mfilesService)
    {
        $this->mfilesService = $mfilesService;
    }

    public function index()
    {
        try {
            $data = $this->mfilesService->getClients();
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clients',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:1',
        ]);

        try {
            $data = $this->mfilesService->createClient($request->name);
            return response()->json($data, 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client',
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
