<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetupDevEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets up the development environment';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Setting up development environment');
        $this->MigrateAndSeedDatabase();
        $user = $this->CreateJohnDoeUser();
        $this->CreatePersonalAccessToken($user);


        $this->info('All done. Bye!');
    }

    public function MigrateAndSeedDatabase()
    {
        $this->call('migrate:fresh');
        $this->call('db:seed');
    }
    public function CreateJohnDoeUser()
    {
        $this->info('Creating John Doe user');
        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->info('John Doe created');
        $this->warn('Email: john@example.com');
        $this->warn('Password: secret');

        return $user;
    }

    public function CreatePersonalAccessToken($user)
    {
        $token = $user->createToken('API Token')->plainTextToken;
        $this->info('Personal access token created successfully.');
        $this->warn("Personal access token:");
        $this->line($token);
    }
}
