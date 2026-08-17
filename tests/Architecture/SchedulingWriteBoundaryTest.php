<?php

it('keeps appointment persistence behind scheduling use-case commands', function () {
    $root = dirname(__DIR__, 2);
    $surfaceDirectories = [
        $root.'/app/Http/Controllers',
        $root.'/app/Filament',
        $root.'/resources/js',
    ];
    $forbidden = [
        'SchedulingOperations\\Models\\Appointment',
        'SchedulingOperations\\Models\\AppointmentSegment',
        "DB::table('appointments')",
        'DB::table("appointments")',
        "DB::table('appointment_segments')",
        'DB::table("appointment_segments")',
    ];

    foreach ($surfaceDirectories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            foreach ($forbidden as $needle) {
                expect($contents)->not->toContain($needle, "{$file->getPathname()} must call a scheduling command instead of writing appointment timestamps.");
            }
        }
    }
});
