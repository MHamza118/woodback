<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Questionnaire;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all existing questionnaires to support mobile camera formats
        $questionnaires = Questionnaire::all();
        
        foreach ($questionnaires as $questionnaire) {
            $questions = $questionnaire->questions;
            $updated = false;
            
            if (is_array($questions)) {
                foreach ($questions as $key => $question) {
                    if (isset($question['type']) && $question['type'] === 'file') {
                        // Update accept attribute to include mobile camera formats
                        if (isset($question['accept'])) {
                            $currentAccept = $question['accept'];
                            // Add mobile formats if not already present
                            if (!str_contains($currentAccept, 'image/*')) {
                                $questions[$key]['accept'] = $currentAccept . ',.heic,.heif,.webp,image/*';
                                $updated = true;
                            }
                        } else {
                            // Set default accept if not present
                            $questions[$key]['accept'] = '.pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.heic,.heif,.webp,image/*';
                            $updated = true;
                        }
                    }
                }
                
                if ($updated) {
                    $questionnaire->questions = $questions;
                    $questionnaire->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert accept formats to original
        $questionnaires = Questionnaire::all();
        
        foreach ($questionnaires as $questionnaire) {
            $questions = $questionnaire->questions;
            $updated = false;
            
            if (is_array($questions)) {
                foreach ($questions as $key => $question) {
                    if (isset($question['type']) && $question['type'] === 'file') {
                        if (isset($question['accept'])) {
                            // Restore original accept formats
                            $questions[$key]['accept'] = '.pdf,.jpg,.jpeg,.png,.gif,.doc,.docx';
                            $updated = true;
                        }
                    }
                }
                
                if ($updated) {
                    $questionnaire->questions = $questions;
                    $questionnaire->save();
                }
            }
        }
    }
};
