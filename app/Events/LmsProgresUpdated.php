<?php

namespace App\Events;

use App\Models\LMS\LmsCourses;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LmsProgresUpdated
{
    use Dispatchable, SerializesModels;

    public $user;
    public $course;

    public function __construct(User $user, LmsCourses $course)
    {
        $this->user = $user;
        $this->course = $course;
    }
}
