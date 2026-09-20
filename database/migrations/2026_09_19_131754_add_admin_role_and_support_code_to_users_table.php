<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('support_code', 22)->nullable()->unique();
            $table->string('admin_role')->nullable()->index();
        });

        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $alphabetLength = mb_strlen($alphabet);

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($alphabet, $alphabetLength): void {
                foreach ($users as $user) {
                    do {
                        $characters = '';

                        for ($index = 0; $index < 12; $index++) {
                            $characters .= $alphabet[random_int(0, $alphabetLength - 1)];
                        }

                        $supportCode = 'ZQ-'.implode('-', str_split($characters, 4));
                    } while (DB::table('users')->where('support_code', $supportCode)->exists());

                    DB::table('users')->where('id', $user->id)->update(['support_code' => $supportCode]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['support_code']);
            $table->dropIndex(['admin_role']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['support_code', 'admin_role']);
        });
    }
};
