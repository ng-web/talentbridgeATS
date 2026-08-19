<?php

namespace App\Providers;

use App\Listeners\SecurityEventSubscriber;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Observers\ApplicantDocumentObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::subscribe(SecurityEventSubscriber::class);

        $documentObserver = app(ApplicantDocumentObserver::class);
        JobSeekerDocument::observe($documentObserver);
        ApplicationFile::observe($documentObserver);
        Application::observe($documentObserver);
        JobSeeker::observe($documentObserver);

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
