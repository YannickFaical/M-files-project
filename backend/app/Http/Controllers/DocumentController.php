<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Services\MFilesService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private $mfilesService;

    public function __construct(MFilesService $mfilesService)
    {
        $this->mfilesService = $mfilesService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:1',
        ]);
        try {
            $data = $this->mfilesService->createDocument($request->input('name'));
            return response()->json($data, 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create document',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('perPage', 20));
            $data = $this->mfilesService->getDocuments($page, $perPage);
            $items = $data['Items'] ?? $data;
            $count = is_array($items) ? count($items) : 0;
            return response()->json([
                'items' => $items,
                'page' => $page,
                'perPage' => $perPage,
                'hasMore' => $count === $perPage,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
        $request->validate([
            'file' => 'required|file',
            'name' => 'required|string|min:1',
        ]);

        try {
            $data = $this->mfilesService->uploadDocument($request->file('file'), $request->input('name'));
            return response()->json($data, 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function files(int $objectId)
    {
        try {
            $data = $this->mfilesService->getDocumentFiles($objectId);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list document files',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function download(int $objectId, ?int $fileId = null)
    {
        try {
            if ($fileId === null) {
                $files = $this->mfilesService->getDocumentFiles($objectId);
                $first = $files['Items'][0] ?? $files[0] ?? null;
                if (!$first || !isset($first['ID'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No file found for this document',
                    ], 404);
                }
                $fileId = (int) $first['ID'];
            }

            $guzzleResponse = $this->mfilesService->getFileContentResponse($objectId, $fileId);

            $headers = [];
            foreach ($guzzleResponse->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            return new StreamedResponse(function () use ($guzzleResponse) {
                echo $guzzleResponse->getBody()->getContents();
            }, 200, $headers);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function show(int $id)
    {
        try {
            $data = $this->mfilesService->getDocument($id);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document',
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
            $data = $this->mfilesService->updateDocumentName($id, $request->input('name'));
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    public function destroy(int $id)
    {
        try {
            $data = $this->mfilesService->deleteDocument($id);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
