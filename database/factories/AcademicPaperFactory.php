<?php

namespace Database\Factories;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPaper>
 */
class AcademicPaperFactory extends Factory
{
    private const TITLES = [
        'Design and Development of a Smart Library Management System',
        'Web-Based Academic Resource Tracking and Borrowing System',
        'Mobile Application for Campus Facility Reservation',
        'IoT-Based Environmental Monitoring for Academic Buildings',
        'Development of an Online Student Document Request Portal',
        'Solar-Powered Charging Station for Campus Devices',
        'Machine Learning Model for Student Performance Prediction',
        'Secure QR Code Attendance Monitoring System',
        'Cloud-Based Inventory Management for Engineering Laboratories',
        'Flood Monitoring and Early Warning System Using IoT Sensors',
        'Web-Based Thesis Catalog and Search Platform',
        'Smart Waste Segregation System Using Computer Vision',
        'Development of a Campus Navigation Mobile Application',
        'Renewable Energy Monitoring System for University Facilities',
        'Design of a Low-Cost Automated Irrigation Controller',
        'Network Performance Monitoring for Academic Computer Laboratories',
        'Cybersecurity Awareness Platform for College Students',
        'Development of an Online Laboratory Equipment Reservation System',
        'Intelligent Traffic Monitoring for Campus Roadways',
        'Automated Room Temperature and Lighting Control System',
        'E-Learning Platform with Offline Course Material Access',
        'Computer-Aided Design of a Modular Emergency Shelter',
        'Structural Health Monitoring Using Wireless Sensor Networks',
        'Development of a School Clinic Appointment Management System',
        'Energy-Efficient Street Lighting Control Using Microcontrollers',
        'Data Visualization Dashboard for Academic Performance Analytics',
        'Web-Based Alumni Information and Engagement Portal',
        'Development of a Voice-Assisted Campus Information System',
        'Design of a Portable Water Quality Testing Device',
        'Predictive Maintenance System for Laboratory Equipment',
        'Smart Parking Slot Detection and Guidance System',
        'Development of a Digital Queue Management Application',
        'Geographic Information System for Campus Asset Mapping',
        'Automated Fire Detection and Notification System',
        'Development of a Student Organization Management Platform',
        'Wireless Sensor Network for Indoor Air Quality Monitoring',
        'Design of a Microcontroller-Based Solar Tracking System',
        'Online Academic Advising and Appointment Scheduling System',
        'Computer Vision-Based Personal Protective Equipment Detection',
        'Development of a Digital Document Archiving System',
        'Smart Classroom Occupancy Monitoring Using Sensor Networks',
        'Analysis of Network Security Risks in Educational Institutions',
        'Development of a Disaster Preparedness Information Application',
        'Automated Feedback Collection System for Student Services',
        'Design of a Low-Power Weather Monitoring Station',
        'Web-Based Engineering Project Collaboration Platform',
        'Development of an RFID-Based Equipment Lending System',
        'Intelligent Energy Consumption Forecasting for Campus Buildings',
        'Mobile-Based Reporting System for Campus Maintenance Requests',
        'Design and Fabrication of a Pedal-Assisted Electric Generator',
        'Development of a Digital Examination Scheduling System',
        'IoT-Based Water Level Monitoring and Alert System',
        'Secure Role-Based Access Control for Student Portals',
        'Development of a Campus Transportation Tracking Application',
        'Structural Analysis of a Lightweight Modular Footbridge',
        'Machine Learning Approach to Academic Risk Classification',
        'Design of a Smart Ventilation Control System',
        'Development of a Community Flood Reporting Application',
        'Network-Based Laboratory File Sharing and Access System',
        'Development of an Accessible Campus Services Website',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            'Civil Engineering',
            'Information Technology',
            'Electrical Engineering',
        ];

        $department = $this->faker->randomElement($departments);

        // Get random dean (expects to be seeded first)
        $dean = Dean::inRandomOrder()->first();

        return [
            'title' => $this->faker->unique()->randomElement(self::TITLES),
            'publication_year' => $this->faker->numberBetween(2002, 2025),
            'paper_type' => $this->faker->randomElement([
                'Thesis', 'Feasib', 'Capstone', 'Research', 'Practicum', 'Report',
            ]),
            'research_adviser_id' => ResearchAdviser::inRandomOrder()->first()?->id,
            'technical_adviser_id' => TechnicalAdviser::inRandomOrder()->first()?->id,
            'department' => $department,
            'dean_id' => $dean?->id,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($academicPaper) {
            // Attach 1-4 random authors if any exist
            $authorIds = Author::inRandomOrder()->limit(rand(1, 4))->pluck('id');
            if ($authorIds->count()) {
                $academicPaper->authors()->attach($authorIds);
            }
        });
    }
}
