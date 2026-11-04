<?php

namespace App\Providers;

use App\Domain\RuleEngine\AssessmentEngine;
use App\Domain\RuleEngine\ClassificationEngine;
use App\Domain\RuleEngine\RuleEvaluator;
use App\Domain\RuleEngine\RuleSetResolver;
use App\Domain\RuleEngine\RuleTestRunner;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleEvaluator::class);
        $this->app->singleton(RuleSetResolver::class);
        $this->app->singleton(ClassificationEngine::class);
        $this->app->singleton(RuleTestRunner::class);
        $this->app->singleton(AssessmentEngine::class);
    }
}
