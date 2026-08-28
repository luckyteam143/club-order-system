<?php

use App\Models\MacronCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The three 2026 catalogs supplied when the module was first built.
     * Idempotent on name + year so re-running never duplicates them; edits
     * made in the admin afterwards are preserved on the non-key fields only
     * when they still match, otherwise this restores the originals.
     */
    private const CATALOGS = [
        ['name' => 'Football', 'link' => 'https://drive.google.com/file/d/1ekwXSK9YCnTewwmtBwNjNqT6TObLhVBb/view', 'sort_order' => 1],
        ['name' => 'Rugby', 'link' => 'https://drive.google.com/file/d/1JdV3DNtekEGLjXStt6IxK8INoX69x1kr/view?usp=sharing', 'sort_order' => 2],
        ['name' => 'Sports', 'link' => 'https://drive.google.com/file/d/15lH_N26Tag9arpVwEZQucimB4weLokQf/view?usp=sharing', 'sort_order' => 3],
    ];

    public function up(): void
    {
        foreach (self::CATALOGS as $catalog) {
            MacronCatalog::firstOrCreate(
                ['name' => $catalog['name'], 'year' => '2026'],
                ['link' => $catalog['link'], 'sort_order' => $catalog['sort_order'], 'show_in_menu' => true],
            );
        }
    }

    public function down(): void
    {
        MacronCatalog::where('year', '2026')
            ->whereIn('name', array_column(self::CATALOGS, 'name'))
            ->delete();
    }
};
