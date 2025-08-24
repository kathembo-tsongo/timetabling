<?php

if (!function_exists('getSchoolName')) {
    function getSchoolName($schoolCode) {
        $schools = [
            'SCES' => 'School of Computing and Engineering Sciences',
            'SBS' => 'School of Business Studies', 
            'SLS' => 'School of Legal Studies',
            'SHS' => 'School of Health Sciences',
            'TOURISM' => 'School of Tourism and Hospitality',
            'SHM' => 'School of Humanities',
        ];
        
        return $schools[$schoolCode] ?? $schoolCode;
    }
}

if (!function_exists('getValidSchoolCodes')) {
    function getValidSchoolCodes() {
        return ['SCES', 'SBS', 'SLS', 'SHS', 'TOURISM', 'SHM'];
    }
}

if (!function_exists('getUserSchoolCode')) {
    function getUserSchoolCode($user) {
        // First try to get from role
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }

        // Fallback to schools column
        return $user->schools ? strtoupper($user->schools) : null;
    }
}