<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyDocumentFileRequest;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicVerificationController extends Controller
{
    public function lookup(): Response
    {
        return Inertia::render('Public/Verify/Lookup');
    }

    public function token(Request $request, string $token, MedicalDocumentVerificationService $service): Response
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $token), 404);

        $method = $request->string('source')->toString() === 'camera' ? 'QR_CAMERA' : 'QR_LINK';

        return Inertia::render('Public/Verify/Result', $service->byToken($token, $request->string('identity_last4')->toString(), $method) + ['challenge' => ['method' => 'token', 'source' => $method]]);
    }

    public function code(Request $request, MedicalDocumentVerificationService $service): Response
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:40'], 'identity_last4' => ['nullable', 'digits:4'], 'source' => ['nullable', 'in:MANUAL_CODE,PDF_HASH']]);

        $method = $validated['source'] ?? 'MANUAL_CODE';

        return Inertia::render('Public/Verify/Result', $service->byCode($validated['code'], $validated['identity_last4'] ?? null, $method) + ['challenge' => ['method' => 'code', 'code' => $validated['code'], 'source' => $method]]);
    }

    public function file(VerifyDocumentFileRequest $request, MedicalDocumentVerificationService $service): Response
    {
        $result = $service->byFile($request->file('document')->getRealPath(), $request->string('identity_last4')->toString());

        return Inertia::render('Public/Verify/Result', $result + ['challenge' => ['method' => 'code', 'code' => $result['document']['code'] ?? '', 'source' => 'PDF_HASH']]);
    }
}
