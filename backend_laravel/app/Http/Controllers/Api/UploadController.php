<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Upload a single high-resolution room photograph.
     */
    public function uploadRoomImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $file = $request->file('image');
        $filename = 'room-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $destinationPath = public_path('uploads/rooms');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $file->move($destinationPath, $filename);
        $relativeUrl = '/uploads/rooms/' . $filename;

        return response()->json([
            'success' => true,
            'message' => 'Room photo uploaded successfully.',
            'image_url' => $relativeUrl,
            'filename' => $filename,
            'size' => filesize($destinationPath . '/' . $filename),
        ]);
    }

    /**
     * Batch upload multiple gallery images.
     */
    public function uploadMultiple(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        $urls = [];
        $destinationPath = public_path('uploads/rooms');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        foreach ($request->file('images') as $file) {
            $filename = 'room-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $filename);
            $urls[] = '/uploads/rooms/' . $filename;
        }

        return response()->json([
            'success' => true,
            'image_urls' => $urls,
            'count' => count($urls),
        ]);
    }
}
