<?php
/**
 * Timezone & Meeting Scheduling Optimizer
 * Converts free timings across worldwide timezones and calculates optimal meeting overlap.
 */

class TimezoneHelper {

    /**
     * Common world timezones with country and offset
     */
    public static function getCommonTimezones(): array {
        return [
            'America/New_York'     => 'USA (Eastern) - New York [UTC-4 / UTC-5]',
            'America/Chicago'      => 'USA (Central) - Chicago [UTC-5 / UTC-6]',
            'America/Denver'       => 'USA (Mountain) - Denver [UTC-6 / UTC-7]',
            'America/Los_Angeles'  => 'USA (Pacific) - Los Angeles [UTC-7 / UTC-8]',
            'Europe/London'        => 'United Kingdom - London [GMT / BST]',
            'Europe/Paris'         => 'Europe (Central) - Paris / Berlin [CET / CEST]',
            'Asia/Dubai'           => 'UAE - Dubai [GST / UTC+4]',
            'Asia/Kolkata'         => 'India - New Delhi / Mumbai [IST / UTC+5:30]',
            'Asia/Singapore'       => 'Singapore / Malaysia [SGT / UTC+8]',
            'Asia/Tokyo'           => 'Japan - Tokyo [JST / UTC+9]',
            'Australia/Sydney'     => 'Australia - Sydney [AEST / AEDT]',
            'UTC'                  => 'Coordinated Universal Time (UTC)'
        ];
    }

    /**
     * Convert a local time on a given date to HR timezone
     */
    public static function convertToHRTime(string $timeStr, string $internTimezone, string $hrTimezone, ?string $date = null): array {
        $date = $date ?: date('Y-m-d');
        try {
            $dt = new DateTime("{$date} {$timeStr}", new DateTimeZone($internTimezone));
            $internDisplay = $dt->format('h:i A T');
            $internFull = $dt->format('Y-m-d H:i');

            // Convert to HR timezone
            $dt->setTimezone(new DateTimeZone($hrTimezone));
            $hrDisplay = $dt->format('h:i A T');
            $hrFull = $dt->format('Y-m-d H:i');
            $dayDiff = (int)$dt->format('Ymd') - (int)date('Ymd', strtotime($date));

            return [
                'success' => true,
                'intern_time' => $internDisplay,
                'intern_full' => $internFull,
                'hr_time' => $hrDisplay,
                'hr_full' => $hrFull,
                'day_offset' => $dayDiff, // +1 if next day, -1 if previous day
                'summary' => "{$internDisplay} ({$internTimezone}) = {$hrDisplay} ({$hrTimezone})"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Compute 24-Hour Overlap Matrix in HR's Timezone
     * 
     * @param string $meetingDate Format: 'YYYY-MM-DD'
     * @param string $hrTimezone  e.g. 'Asia/Kolkata' or 'America/New_York'
     * @param array  $interns     Array of interns with their availability windows
     * @return array Matrix with slots, heatmap data, and recommended meeting windows
     */
    public static function calculateOverlapMatrix(string $meetingDate, string $hrTimezone, array $interns): array {
        $totalInterns = count($interns);
        if ($totalInterns === 0) {
            return [
                'slots' => [],
                'recommendations' => [],
                'total_interns' => 0
            ];
        }

        $hrTz = new DateTimeZone($hrTimezone);
        $utcTz = new DateTimeZone('UTC');

        // Create 48 thirty-minute slots in HR's day
        $slots = [];
        $slotDurationMinutes = 30;

        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $timeHrStr = sprintf('%02d:%02d:00', $hour, $minute);
                $slotDtHr = new DateTime("{$meetingDate} {$timeHrStr}", $hrTz);
                
                // Convert HR slot to UTC to compare universally
                $slotDtUtc = clone $slotDtHr;
                $slotDtUtc->setTimezone($utcTz);

                $availableCount = 0;
                $attendeeStatus = [];

                foreach ($interns as $intern) {
                    $internTz = new DateTimeZone($intern['timezone'] ?? 'UTC');
                    
                    // Convert UTC slot time to this intern's local time
                    $internLocalDt = clone $slotDtUtc;
                    $internLocalDt->setTimezone($internTz);

                    $internDayOfWeek = (int)$internLocalDt->format('N'); // 1 (Mon) - 7 (Sun)
                    $internTimeOnly = $internLocalDt->format('H:i:s');
                    $internDisplayTime = $internLocalDt->format('h:i A T');

                    $isAvailable = false;
                    if (!empty($intern['availability'])) {
                        foreach ($intern['availability'] as $window) {
                            if ((int)$window['day_of_week'] === $internDayOfWeek) {
                                $start = $window['start_time'];
                                $end = $window['end_time'];
                                if ($internTimeOnly >= $start && $internTimeOnly < $end) {
                                    $isAvailable = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($isAvailable) {
                        $availableCount++;
                    }

                    $attendeeStatus[$intern['id']] = [
                        'name' => $intern['name'],
                        'country' => $intern['country'],
                        'timezone' => $intern['timezone'],
                        'local_time' => $internDisplayTime,
                        'is_available' => $isAvailable
                    ];
                }

                $percentage = ($totalInterns > 0) ? round(($availableCount / $totalInterns) * 100) : 0;

                // Color code level: 0 = none, 1 = low, 2 = medium, 3 = high, 4 = full
                $level = 'none';
                if ($percentage === 100) {
                    $level = 'full';
                } elseif ($percentage >= 70) {
                    $level = 'high';
                } elseif ($percentage >= 40) {
                    $level = 'medium';
                } elseif ($percentage > 0) {
                    $level = 'low';
                }

                $slots[] = [
                    'time_hr' => sprintf('%02d:%02d', $hour, $minute),
                    'display_hr' => $slotDtHr->format('h:i A'),
                    'full_hr' => $slotDtHr->format('h:i A T'),
                    'available_count' => $availableCount,
                    'total_interns' => $totalInterns,
                    'percentage' => $percentage,
                    'level' => $level,
                    'attendee_status' => $attendeeStatus
                ];
            }
        }

        // Calculate continuous 1-hour and 30-min recommended meeting windows
        $recommendations = self::findOptimalMeetingWindows($slots, $totalInterns, 2); // 2 blocks = 1 hour

        return [
            'date' => $meetingDate,
            'hr_timezone' => $hrTimezone,
            'total_interns' => $totalInterns,
            'slots' => $slots,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Find best consecutive windows (e.g. 1 hour = 2 consecutive 30-min blocks)
     */
    private static function findOptimalMeetingWindows(array $slots, int $totalInterns, int $consecutiveBlocks = 2): array {
        $candidates = [];
        $totalSlots = count($slots);

        for ($i = 0; $i <= $totalSlots - $consecutiveBlocks; $i++) {
            $windowSlots = array_slice($slots, $i, $consecutiveBlocks);
            
            // Check availability for all interns across this entire block
            $internsAlwaysAvailable = [];
            $allInternIds = array_keys($windowSlots[0]['attendee_status']);

            foreach ($allInternIds as $internId) {
                $availableInAll = true;
                foreach ($windowSlots as $ws) {
                    if (!$ws['attendee_status'][$internId]['is_available']) {
                        $availableInAll = false;
                        break;
                    }
                }
                if ($availableInAll) {
                    $internsAlwaysAvailable[] = $internId;
                }
            }

            $availableCount = count($internsAlwaysAvailable);
            $percentage = ($totalInterns > 0) ? round(($availableCount / $totalInterns) * 100) : 0;

            $startTime = $windowSlots[0]['display_hr'];
            $endTime = date('h:i A', strtotime($windowSlots[count($windowSlots) - 1]['display_hr'] . ' +30 minutes'));

            // Build detailed breakdown of each intern's local time for this window
            $internBreakdown = [];
            foreach ($allInternIds as $internId) {
                $info = $windowSlots[0]['attendee_status'][$internId];
                $internBreakdown[] = [
                    'id' => $internId,
                    'name' => $info['name'],
                    'country' => $info['country'],
                    'timezone' => $info['timezone'],
                    'local_start' => $info['local_time'],
                    'is_available' => in_array($internId, $internsAlwaysAvailable, true)
                ];
            }

            $candidates[] = [
                'start_time_hr' => $windowSlots[0]['time_hr'],
                'end_time_hr' => date('H:i', strtotime($windowSlots[0]['time_hr'] . ' +' . ($consecutiveBlocks * 30) . ' minutes')),
                'time_range_hr' => "{$startTime} - {$endTime}",
                'available_count' => $availableCount,
                'total_interns' => $totalInterns,
                'percentage' => $percentage,
                'intern_breakdown' => $internBreakdown,
                'score' => $availableCount * 1000 - $i // prefer higher attendance, earlier in day as tiebreaker
            ];
        }

        // Sort descending by score
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top 5 distinct recommended slots
        return array_slice($candidates, 0, 5);
    }
}
