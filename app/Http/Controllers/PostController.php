<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\PostAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    public function createPost(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'caption' => 'required|string',
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,webp,png,gif|max:2048', // Maksimal ukuran 2MB per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Simpan data post
        $post = Post::create([
            'user_id' => $request->user()->id,
            'caption' => $request->caption,
        ]);

        // Proses unggah file dan simpan ke tabel 'post_attachments'
        foreach ($request->file('attachments') as $file) {
            // Tentukan path untuk penyimpanan file
            $path = $file->store('posts', 'public'); // Simpan file di folder 'posts'

            // Simpan data file ke tabel 'post_attachments'
            PostAttachment::create([
                'post_id' => $post->id,
                'storage_path' => $path,
            ]);
        }

        return response()->json([
            'message' => 'Create post success',
        ], 201);
    }

    public function deletePost($id)
    {
        // Cek apakah post dengan ID yang diberikan ada
        $post = Post::find($id);

        if (!$post) {
            // Jika post tidak ditemukan, kembalikan response 404
            return response()->json([
                'message' => 'Post not found'
            ], 404);
        }

        // Cek apakah user yang sedang login adalah pemilik post
        if ($post->user_id !== Auth::user()->id) {
            // Jika bukan pemilik post, kembalikan response 403
            return response()->json([
                'message' => 'Forbidden access'
            ], 403);
        }

        $post_attachments = PostAttachment::where('post_id', $post->id)->get();

        foreach ($post_attachments as $attachment) {
            // Hapus file fisik jika ada
            if (Storage::exists('public/' . $attachment->file_path)) {
                Storage::delete('public/' . $attachment->file_path);
            }

            // Hapus entri lampiran dari database
            $attachment->delete();
        }
        // Hapus post dan semua lampiran terkait (jika ada)
        $post->delete();

        // Kembalikan response sukses 204 (no content)
        return response()->json(null, 204);
    }

    public function getPosts(Request $request)
    {
        // Validasi parameter page dan size
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:0',
            'size' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Set default page dan size jika tidak ada
        $page = $request->get('page', 0);
        $size = $request->get('size', 10);

        // Ambil data post dengan paginasi
        $postsQuery = Post::with('user', 'attachments')
            ->whereNull('deleted_at') // Pastikan hanya post yang tidak terhapus yang diambil
            ->orderBy('created_at', 'desc'); // Urutkan berdasarkan tanggal dibuat

        if ($page == 0) {
            $posts = $postsQuery->skip($page * $size)->take($size)->get();
        } else {
            $posts = $postsQuery->skip((($page - 1) * $size) + 10)->take($size)->get();
        }

        // Format data untuk response
        $response = [
            'page' => $page,
            'size' => $size,
            'posts' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'caption' => $post->caption,
                    'created_at' => $post->created_at,
                    'deleted_at' => $post->deleted_at,
                    'user' => [
                        'id' => $post->user->id,
                        'full_name' => $post->user->full_name,
                        'username' => $post->user->username,
                        'bio' => $post->user->bio,
                        'is_private' => $post->user->is_private,
                        'created_at' => $post->user->created_at,
                    ],
                    'attachments' => $post->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'storage_path' => $attachment->storage_path,
                        ];
                    }),
                ];
            }),
        ];

        return response()->json($response, 200);
    }

    public function getImage($imageName)
    {
        try {
            // Get the path of the image
            $imagePath = storage_path("app/public/posts/{$imageName}");

            if (!file_exists($imagePath)) {
                return response()->json(['error' => 'Image not found.'], 404);
            }

            // Read the image file and convert it to base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            return response()->json([
                'base64' => 'data:' . $mimeType . ';base64,' . $imageData
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing the image.'], 500);
        }
    }
}
