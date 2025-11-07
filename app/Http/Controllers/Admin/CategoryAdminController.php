<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryAdminController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string',
            'only_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $q = Category::query()
            ->when($request->boolean('only_active', false), fn($qq) => $qq->where('is_active', true))
            ->when($request->filled('q'), function ($qq) use ($request) {
                $term = '%' . str_replace('%', '\\%', $request->string('q')) . '%';
                $qq->where(function ($sub) use ($term) {
                    $sub->where('key', 'like', $term)->orWhere('label', 'like', $term);
                });
            });

        if ($request->filled('per_page')) {
            $rows = $q->orderBy('label')->paginate((int)$request->integer('per_page'));
        } else {
            $rows = $q->orderBy('label')->get();
        }

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string|max:100|unique:categories,key',
            'label' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $row = Category::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['status' => 'success', 'data' => $row], 201);
    }

    public function update($id, Request $request)
    {
        $row = Category::findOrFail($id);
        $data = $request->validate([
            'key' => 'sometimes|required|string|max:100|unique:categories,key,' . $row->id,
            'label' => 'sometimes|required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $row->fill($data);
        if ($request->has('is_active')) {
            $row->is_active = (bool)$request->boolean('is_active');
        }
        $row->save();

        return response()->json(['status' => 'success', 'data' => $row]);
    }

    public function destroy($id)
    {
        $row = Category::findOrFail($id);
        $row->delete();
        return response()->json(['status' => 'success', 'message' => 'Catégorie supprimée.']);
    }
}
