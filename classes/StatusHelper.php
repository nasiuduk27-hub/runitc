<?php

class StatusHelper
{
    public static function getStatusText($status): string
    {
        $stat = (string) trim($status);

        switch ($stat) {
            case '0':
                return 'Raw Authorize';
            case '1':
                return 'Authorize';
            case '2':
                return 'Readiness';
            case '3':
                return 'Ready to enter CBT';
            case '4':
                return 'Questioner';
            case '5':
                return 'Regulation & Confidentiality Agrement';
            case '6':
                return 'Ready to Test';
            case '7':
                return 'End of Test';
            case '8':
                return 'Collected';
            case '9':
                return 'Submit';
            default:
                if (strtolower($stat) === 'c') {
                    return 'Completed';
                }

                if (strtolower($stat) === 'a') {
                    return 'Active';
                }

                return $stat . ' - Unknown';
        }
    }

    public static function getStatusBadge($status, $ke_suspend = 0): string
    {
        $stat = (string) trim($status);
        $text = self::getStatusText($status);

        if ((int) $ke_suspend === 1) {
            return '<span class="bg-red-500 text-white text-xs px-3 py-1.5 font-bold rounded border border-red-600 shadow-sm animate-pulse" title="Peserta sedang di-suspend / pause oleh Admin"><i class="fas fa-pause-circle mr-1"></i>SUSPENDED</span>';
        }

        switch ($stat) {
            case '0':
                return '<span class="bg-gray-100 text-gray-700 text-xs px-3 py-1.5 font-bold rounded border border-gray-200">' . $text . '</span>';
            case '1':
                return '<span class="bg-orange-100 text-orange-700 text-xs px-3 py-1.5 font-bold rounded border border-orange-200">' . $text . '</span>';
            case '2':
                return '<span class="bg-blue-100 text-blue-700 text-xs px-3 py-1.5 font-bold rounded border border-blue-200">' . $text . '</span>';
            case '3':
                return '<span class="bg-indigo-100 text-indigo-700 text-xs px-3 py-1.5 font-bold rounded border border-indigo-200">' . $text . '</span>';
            case '4':
                return '<span class="bg-teal-100 text-teal-700 text-xs px-3 py-1.5 font-bold rounded border border-teal-200">' . $text . '</span>';
            case '5':
                return '<span class="bg-green-100 text-green-700 text-xs px-3 py-1.5 font-bold rounded border border-green-200">' . $text . '</span>';
            case '6':
                return '<span class="bg-green-200 text-green-800 text-xs px-3 py-1.5 font-bold rounded border border-green-300">' . $text . '</span>';
            case '7':
                return '<span class="bg-red-100 text-red-700 text-xs px-3 py-1.5 font-bold rounded border border-red-200">' . $text . '</span>';
            case '8':
                return '<span class="bg-green-100 text-green-700 text-xs px-3 py-1.5 font-bold rounded border border-green-200">' . $text . '</span>';
            case '9':
                return '<span class="bg-purple-100 text-purple-700 text-xs px-3 py-1.5 font-bold rounded border border-purple-200">' . $text . '</span>';
            default:
                return '<span class="bg-gray-100 text-gray-700 text-xs px-3 py-1.5 font-bold rounded border border-gray-200">' . $text . '</span>';
        }
    }
}
