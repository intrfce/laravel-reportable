<?php

namespace Intrfce\LaravelReportable\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeReportableCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:reportable {name : The name of the reportable class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new reportable class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Reportable';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/reportable.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Reportables';
    }
}
