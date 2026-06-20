<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exercises = [
            // ---------------------------------------------------------
            // CHEST
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Barbell Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Incline Barbell Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Decline Barbell Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Dumbbell Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Incline Dumbbell Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Decline Dumbbell Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Machine Chest Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Smith Machine Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Smith Machine Incline Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Push-Up', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Weighted Push-Up', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Chest Dips', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            ['name' => 'Guillotine Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound'],
            // Isolation
            ['name' => 'Flat Dumbbell Fly', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Incline Dumbbell Fly', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Decline Dumbbell Fly', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Pec Deck Fly', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Crossover', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Low Cable Crossover', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'High Cable Crossover', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Pullover', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],
            ['name' => 'Svens Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // BACK
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Deadlift', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Sumo Deadlift', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Trap Bar Deadlift', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Pull-Up', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Weighted Pull-Up', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Chin-Up', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Barbell Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Pendlay Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Yates Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'One-Arm Dumbbell Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'T-Bar Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Lat Pulldown', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Close-Grip Lat Pulldown', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Reverse-Grip Lat Pulldown', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Seated Cable Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Chest-Supported Dumbbell Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Chest-Supported T-Bar Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Meadows Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Rack Pull', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            ['name' => 'Good Morning', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound'],
            // Isolation
            ['name' => 'Straight-Arm Cable Pulldown', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Pullover', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Isolation'],
            ['name' => 'Hyperextension (Back Extension)', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // LEGS
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Barbell Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Front Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Box Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Zercher Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Leg Press', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Hack Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Romanian Deadlift (RDL)', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Stiff-Legged Deadlift', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Bulgarian Split Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Dumbbell Walking Lunges', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Barbell Reverse Lunges', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Goblet Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Glute-Ham Raise', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Barbell Hip Thrust', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Machine Glute Drive', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            ['name' => 'Step-Ups', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound'],
            // Isolation
            ['name' => 'Leg Extension', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Lying Leg Curl', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Seated Leg Curl', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Standing Leg Curl', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Hip Abduction Machine', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Hip Adduction Machine', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Pull-Through', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Kickback', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Standing Calf Raise', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Seated Calf Raise', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Leg Press Calf Press', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Donkey Calf Raise', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],
            ['name' => 'Tibialis Raise', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // SHOULDERS
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Barbell Overhead Press (OHP)', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Seated Barbell Overhead Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Dumbbell Shoulder Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Arnold Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Machine Shoulder Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Smith Machine Shoulder Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Push Press', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            ['name' => 'Upright Row', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Compound'],
            // Isolation
            ['name' => 'Dumbbell Lateral Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Lateral Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Machine Lateral Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Front Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Barbell Front Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Front Raise', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Rear Delt Dumbbell Fly', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Reverse Pec Deck (Rear Delt)', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Face Pull', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Shrugs', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Barbell Shrugs', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],
            ['name' => 'Smith Machine Shrugs', 'target_muscle_group' => 'Shoulders', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // BICEPS
            // ---------------------------------------------------------
            // Isolation
            ['name' => 'Barbell Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'EZ-Bar Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Alternate Bicep Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Hammer Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Rope Hammer Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Incline Dumbbell Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Preacher Curl (Barbell/EZ-Bar)', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Machine Preacher Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Concentration Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Straight Bar Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'High Cable Curl (Crucifix Curl)', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Spider Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Reverse Barbell Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Zottman Curl', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // TRICEPS
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Close-Grip Bench Press', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Compound'],
            ['name' => 'Triceps Dips', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Compound'],
            ['name' => 'Bench Dips', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Compound'],
            ['name' => 'Diamond Push-Up', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Compound'],
            // Isolation
            ['name' => 'Cable Triceps Pushdown (Straight Bar)', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Triceps Pushdown (Rope)', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Triceps Pushdown (V-Bar)', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Lying Triceps Extension (Skullcrusher)', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Overhead Dumbbell Triceps Extension', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Overhead Cable Triceps Extension', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Triceps Kickback', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Triceps Kickback', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],
            ['name' => 'Machine Triceps Extension', 'target_muscle_group' => 'Triceps', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // FOREARMS
            // ---------------------------------------------------------
            // Isolation & Compound
            ['name' => 'Barbell Wrist Curl', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Isolation'],
            ['name' => 'Barbell Reverse Wrist Curl', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Isolation'],
            ['name' => 'Dumbbell Wrist Curl', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Isolation'],
            ['name' => 'Behind-the-Back Barbell Wrist Curl', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Isolation'],
            ['name' => 'Plate Pinch', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Isolation'],
            ['name' => 'Farmer\'s Walk', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Compound'],
            ['name' => 'Dead Hang', 'target_muscle_group' => 'Forearms', 'mechanics_type' => 'Compound'],

            // ---------------------------------------------------------
            // CORE / ABS
            // ---------------------------------------------------------
            // Isolation (Most core isolation is bodyweight flexion/rotation)
            ['name' => 'Crunch', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Decline Crunch', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Cable Crunch', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Machine Crunch', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Bicycle Crunch', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Hanging Leg Raise', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Compound'],
            ['name' => 'Hanging Knee Raise', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Compound'],
            ['name' => 'Captain\'s Chair Leg Raise', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Lying Leg Raise', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Ab Wheel Rollout', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Compound'],
            ['name' => 'Russian Twist', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Woodchopper', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Compound'],
            ['name' => 'Plank', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Side Plank', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'V-Ups', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],
            ['name' => 'Flutter Kicks', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation'],

            // ---------------------------------------------------------
            // FULL BODY / OLYMPIC
            // ---------------------------------------------------------
            // Compound
            ['name' => 'Power Clean', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Hang Clean', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Clean and Jerk', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Snatch', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Kettlebell Swing', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Burpee', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound'],
            ['name' => 'Thruster', 'target_muscle_group' => 'Full Body', 'mechanics_type' => 'Compound']
        ];

        foreach ($exercises as $exercise) {
            Exercise::firstOrCreate(
                ['name' => $exercise['name']],
                $exercise
            );
        }
    }
}
