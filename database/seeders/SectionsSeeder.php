<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionsSeeder extends Seeder
{
    /**
     * The production sections, in the same order as the UI (s1–s4 → ids 1–4).
     *
     * @var list<string>
     */
    public const SECTIONS = ['Sudură', 'Vopsitorie', 'Asamblare', 'Control calitate'];

    /**
     * Seed the production sections. Safe to run repeatedly.
     */
    public function run(): void
    {
        foreach (self::SECTIONS as $name) {
            Section::query()->firstOrCreate(['name' => $name]);
        }
    }
}
