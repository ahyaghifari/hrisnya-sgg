<?php

namespace App\Console\Commands;

use App\Models\LMS\LmsQuizzes;
use Illuminate\Console\Command;

class UpdateQuizTotalScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:update-total-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update total scores for all existing quizzes based on their questions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating total scores for all quizzes...');
        
        $quizzes = LmsQuizzes::with('questions')->get();
        $updated = 0;
        
        foreach ($quizzes as $quiz) {
            $oldScore = $quiz->total_score;
            $quiz->updateTotalScore();
            
            if ($oldScore !== $quiz->total_score) {
                $updated++;
                $this->line("Quiz '{$quiz->title}': {$oldScore} → {$quiz->total_score}");
            }
        }
        
        $this->info("Successfully updated {$updated} out of {$quizzes->count()} quizzes.");
        
        return Command::SUCCESS;
    }
}
