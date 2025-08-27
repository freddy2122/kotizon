<?php

namespace App\Http\Controllers;

use App\Models\KycProfile;
use App\Models\User;
use App\Notifications\KycStatusChangedNotification;
use App\Notifications\KycSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class KycController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'national_id'    => 'required|string',
            'document_type'  => 'required|in:CNI,PASSEPORT,PERMIS',
            'document_front' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'document_back'  => 'required|file|mimes:jpg,jpeg,png,pdf',
            'date_of_birth'  => 'required|date',
            'country'        => 'required|string',
            'address'        => 'required|string',
            // Si tu as ajouté selfie, ajoute-le ici :
            'selfie'         => 'nullable|file|mimes:jpg,jpeg,png',
        ]);

        $user = auth()->user();

        $front = $request->file('document_front')->store('kyc_documents');
        $back = $request->file('document_back')->store('kyc_documents');
        $selfie = $request->hasFile('selfie') ? $request->file('selfie')->store('kyc_documents') : null;

        $profile = $user->kycProfile()->create([
            'national_id'    => $request->national_id,
            'document_type'  => $request->document_type,
            'document_front' => $front,
            'document_back'  => $back,
            'date_of_birth'  => $request->date_of_birth,
            'country'        => $request->country,
            'address'        => $request->address,
            'selfie'         => $selfie,
            'status'         => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();

        Notification::send($admins, new KycSubmittedNotification($user));

        return response()->json(['message' => 'KYC soumis avec succès, en attente de validation.']);
    }



    public function index()
    {
        $kycProfiles = KycProfile::with('user')->paginate(20);
        return response()->json($kycProfiles);
    }

    public function show($id)
    {
        $profile = KycProfile::with('user')->findOrFail($id);
        return response()->json($profile);
    }

    public function validateKyc($id)
    {
        $profile = KycProfile::findOrFail($id);
        $profile->update(['status' => 'approved']);

        // Notifier l'utilisateur que KYC validé
        $profile->user->notify(new KycStatusChangedNotification('approved'));

        return response()->json(['message' => 'KYC validé avec succès.']);
    }

    public function rejectKyc($id)
    {
        $profile = KycProfile::findOrFail($id);
        $profile->update(['status' => 'rejected']);

        // Notifier l'utilisateur que KYC rejeté
        $profile->user->notify(new KycStatusChangedNotification('rejected'));

        return response()->json(['message' => 'KYC rejeté avec succès.']);
    }

    public function destroy($id)
    {
        $profile = KycProfile::findOrFail($id);
        $profile->delete();

        return response()->json(['message' => 'KYC supprimé avec succès.']);
    }
}
