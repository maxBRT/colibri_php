<?php

namespace App\Providers;

use App\Repositories\Contracts\LogoRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\SourceRepositoryInterface;
use App\Repositories\Eloquent\LogoRepository;
use App\Repositories\Eloquent\PostRepository;
use App\Repositories\Eloquent\SourceRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SourceRepositoryInterface::class, SourceRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(LogoRepositoryInterface::class, LogoRepository::class);
    }
}
