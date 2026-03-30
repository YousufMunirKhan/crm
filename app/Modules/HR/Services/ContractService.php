<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\EmploymentContract;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Support\Facades\Log;

class ContractService
{
    /**
     * Generate employment contract PDF
     */
    public function generateContract(User $user, array $contractData = []): string
    {
        $defaultData = [
            'employee_name' => $user->name,
            'employee_email' => $user->email,
            'employee_phone' => $user->phone ?? 'N/A',
            'employee_address' => $user->address ?? 'N/A',
            'position' => $user->role->name ?? 'Employee',
            'hire_date' => $user->hire_date ? $user->hire_date->format('d/m/Y') : now()->format('d/m/Y'),
            'employee_type' => $user->employee_type ?? 'N/A',
            'company_name' => config('app.name', 'Company'),
            'contract_date' => now()->format('d/m/Y'),
        ];

        $data = array_merge($defaultData, $contractData);

        // Generate PDF
        $pdf = DomPDF::loadView('contracts.employment_contract', $data);
        
        // Store PDF
        $filename = 'contract_' . $user->id . '_' . time() . '.pdf';
        $path = 'contracts/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());
        
        // Save contract record
        EmploymentContract::create([
            'user_id' => $user->id,
            'template_type' => 'employment',
            'contract_data' => $data,
            'pdf_path' => $path,
        ]);

        // Update user's contract_pdf_path
        $user->update(['contract_pdf_path' => $path]);

        return Storage::disk('public')->path($path);
    }

    /**
     * Send contract via email
     */
    public function sendContract(User $user, ?string $pdfPath = null): bool
    {
        try {
            if (!$pdfPath && $user->contract_pdf_path) {
                $pdfPath = Storage::disk('public')->path($user->contract_pdf_path);
            }

            if (!$pdfPath || !file_exists($pdfPath)) {
                // Generate contract if not exists
                $pdfPath = $this->generateContract($user);
            }

            // Send email with PDF attachment
            \App\Services\MailConfigFromDatabase::apply();
            Mail::send('emails.employment_contract', [
                'user' => $user,
                'company_name' => config('app.name', 'Company'),
            ], function ($message) use ($user, $pdfPath) {
                $message->to($user->email, $user->name)
                    ->subject('Your Employment Contract - ' . config('app.name'))
                    ->attach($pdfPath, [
                        'as' => 'Employment_Contract.pdf',
                        'mime' => 'application/pdf',
                    ]);
            });

            // Update sent_at timestamp
            $user->update(['contract_sent_at' => now()]);
            
            // Update contract record
            EmploymentContract::where('user_id', $user->id)
                ->latest()
                ->first()
                ?->update(['sent_at' => now()]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send employment contract: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate and send contract
     */
    public function generateAndSend(User $user, array $contractData = []): bool
    {
        $this->generateContract($user, $contractData);
        return $this->sendContract($user);
    }
}

