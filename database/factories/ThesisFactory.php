<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Thesis;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thesis>
 */
class ThesisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            'Computer Engineering and Information Technology',
            'Information Technology',
            'Computer Science',
            'Electronics Engineering',
            'Civil Engineering',
            'Mechanical Engineering'
        ];

        $titles = [
            'Development of Web-Based Learning Management System for PLV',
            'Mobile Application for Student Information Management',
            'IoT-Based Smart Campus Security System',
            'Machine Learning Approach to Student Performance Prediction',
            'Blockchain Technology for Academic Records Management',
            'AI-Powered Library Management System',
            'Smart Attendance Monitoring Using Facial Recognition',
            'Design and Implementation of Campus WiFi Network Infrastructure',
            'Digital Library System with QR Code Integration',
            'Student Portal Enhancement with Real-time Notifications'
        ];

        $advisers = [
            'Dr. Maria Santos',
            'Eng. John Cruz',
            'Prof. Ana Reyes',
            'Dr. Carlos Mendoza',
            'Eng. Lisa Garcia',
            'Prof. Robert Tan',
            'Dr. Jennifer Lopez',
            'Eng. Michael Wong'
        ];

        $deans = [
            'Dr. Elena Rodriguez',
            'Dr. Ferdinand Marcos',
            'Dr. Patricia Aquino'
        ];

        return [
            'catalog_code' => 'PLV-' . $this->faker->unique()->numberBetween(1000, 9999),
            'title' => $this->faker->randomElement($titles),
            'copies' => $this->faker->numberBetween(1, 3),
            'research_project_adviser' => $this->faker->randomElement($advisers),
            'department' => $this->faker->randomElement($departments),
            'member1' => $this->faker->name(),
            'member2' => $this->faker->name(),
            'member3' => $this->faker->name(),
            'member4' => $this->faker->name(),
            'dean' => $this->faker->randomElement($deans),
            'status' => $this->faker->randomElement(['Available', 'Reserved', 'Unavailable']),
        ];
    }
}
