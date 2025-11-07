<?php

namespace App\Console\Commands;

use App\Services\ApiService;
use Illuminate\Console\Command;

class TestApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test {action} {--nim=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SIAKAD API connection and endpoints';

    /**
     * Execute the console command.
     */
    public function handle(ApiService $apiService)
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'check':
                $this->checkConnection($apiService);
                break;

            case 'login':
                $this->testLogin($apiService);
                break;

            case 'biodata':
                $this->testBiodata($apiService);
                break;

            default:
                $this->error('Invalid action. Use: check, login, or biodata');
                $this->line('');
                $this->line('Examples:');
                $this->line('  php artisan api:test check');
                $this->line('  php artisan api:test login --nim=2011102441001 --password=yourpassword');
                $this->line('  php artisan api:test biodata --nim=2011102441001');
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function checkConnection(ApiService $apiService)
    {
        $this->info('Checking API connection...');

        if ($apiService->isAvailable()) {
            $this->info('✓ API is available!');
        } else {
            $this->error('✗ API is not available!');
        }
    }

    protected function testLogin(ApiService $apiService)
    {
        $nim = $this->option('nim');
        $password = $this->option('password');

        if (!$nim || !$password) {
            $this->error('NIM and password are required!');
            $this->line('Usage: php artisan api:test login --nim=YOUR_NIM --password=YOUR_PASSWORD');
            return;
        }

        $this->info("Testing login for NIM: {$nim}");

        $result = $apiService->login($nim, $password);

        if ($result) {
            $this->info('✓ Login successful!');
            $this->line('');
            $this->table(
                ['Field', 'Value'],
                [
                    ['UUID', $result['uuid'] ?? '-'],
                    ['User', $result['user'] ?? '-'],
                    ['Nama', $result['nama'] ?? '-'],
                    ['Email', $result['email'] ?? '-'],
                    ['Telepon', $result['telepon'] ?? '-'],
                    ['Prodi', $result['prodi'] ?? '-'],
                    ['Fakultas', $result['fakultas'] ?? '-'],
                    ['Role', $result['role'] ?? '-'],
                ]
            );
        } else {
            $this->error('✗ Login failed!');
        }
    }

    protected function testBiodata(ApiService $apiService)
    {
        $nim = $this->option('nim');

        if (!$nim) {
            $this->error('NIM is required!');
            $this->line('Usage: php artisan api:test biodata --nim=YOUR_NIM');
            return;
        }

        $this->info("Fetching biodata for NIM: {$nim}");

        $result = $apiService->getBiodata($nim);

        if ($result) {
            $this->info('✓ Biodata retrieved successfully!');
            $this->line('');
            $this->table(
                ['Field', 'Value'],
                [
                    ['UUID', $result['uuid'] ?? '-'],
                    ['NIM', $result['nim'] ?? '-'],
                    ['Nama', $result['nama'] ?? '-'],
                    ['Email', $result['email'] ?? '-'],
                    ['Tempat Lahir', $result['tempat_lahir'] ?? '-'],
                    ['Tanggal Lahir', $result['tanggal_lahir'] ?? '-'],
                    ['No HP', $result['no_hp'] ?? '-'],
                    ['Program Studi', $result['program_studi'] ?? '-'],
                    ['Fakultas', $result['fakultas'] ?? '-'],
                    ['Angkatan', $result['angkatan'] ?? '-'],
                    ['Semester', $result['semester'] ?? '-'],
                    ['SKS', $result['sks'] ?? '-'],
                    ['IP', $result['ip'] ?? '-'],
                    ['IPK', $result['ipk'] ?? '-'],
                    ['Status', $result['status_mahasiswa'] ?? '-'],
                ]
            );
        } else {
            $this->error('✗ Failed to retrieve biodata!');
        }
    }
}
