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

    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('perPage', 20));
            $data = $this->mfilesService->getClients($page, $perPage);
            $items = $data['Items'] ?? $data;
            $count = is_array($items) ? count($items) : 0;
            return response()->json([
                'items' => $items,
                'page' => $page,
                'perPage' => $perPage,
                'hasMore' => $count === $perPage,
            ]);
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

    public function show(int $id)
    {
        try {
            $data = $this->mfilesService->getClient($id);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch client',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'required|string|min:1',
        ]);
        try {
            $data = $this->mfilesService->updateClientName($id, $request->input('name'));
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function destroy(int $id)
    {
        try {
            $data = $this->mfilesService->deleteClient($id);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client',
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
