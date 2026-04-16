<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtaSurvey extends Model
{
    use HasFactory;

    protected $table = 'arta_surveys';

    protected $fillable = [
        'client_type',
        'sex',
        'age',
        'region',
        'service_availed',
        'cc1',
        'cc2',
        'cc3',
        'sqd0',
        'sqd1',
        'sqd2',
        'sqd3',
        'sqd4',
        'sqd5',
        'sqd6',
        'sqd7',
        'sqd8',
    ];

    // --- CONSTANTS FOR BACKPACK AND FRONTEND --- //

    public const REGIONS = [
        1 => 'NCR: National Capital Region',
        2 => 'CAR: Cordillera Administrative Region',
        3 => 'Region I: Ilocos Region',
        4 => 'Region II: Cagayan Valley',
        5 => 'Region III: Central Luzon',
        6 => 'Region IV-A: CALABARZON',
        7 => 'Region IV-B: MIMAROPA',
        8 => 'Region V: Bicol Region',
        9 => 'Region VI: Western Visayas',
        10 => 'Region VII: Central Visayas',
        11 => 'Region VIII: Eastern Visayas',
        12 => 'Region IX: Zamboanga Peninsula',
        13 => 'Region X: Northern Mindanao',
        14 => 'Region XI: Davao Region',
        15 => 'Region XII: SOCCSKSARGEN',
        16 => 'Region XIII: Caraga',
        17 => 'BARMM: Bangsamoro Autonomous Region in Muslim Mindanao'
    ];

    public const CC1_OPTIONS = [
        1 => '1. I know what a CC is and I saw this office’s CC.',
        2 => '2. I know what a CC is but I did NOT see this office’s CC.',
        3 => '3. I learned of the CC only when I saw this office’s CC.',
        4 => '4. I do not know what a CC is and I did not see one in this office.',
    ];

    public const CC2_OPTIONS = [
        1 => '1. Easy to see',
        2 => '2. Somewhat easy to see',
        3 => '3. Difficult to see',
        4 => '4. Not visible at all',
        5 => '5. Not Applicable',
    ];

    public const CC3_OPTIONS = [
        1 => '1. Helped very much',
        2 => '2. Somewhat helped',
        3 => '3. Did not help',
        4 => '4. Not Applicable',
    ];

    public const SQD_QUESTIONS = [
        'sqd0' => 'SQD0: I am satisfied with the service that I availed.',
        'sqd1' => 'SQD1: I spent a reasonable amount of time for my transaction.',
        'sqd2' => 'SQD2: The office followed the transaction’s requirements and steps based on the information provided.',
        'sqd3' => 'SQD3: The steps (including payment) I needed to do for my transaction were easy and simple.',
        'sqd4' => 'SQD4: I easily found information about my transaction from the office or its website.',
        'sqd5' => 'SQD5: I paid a reasonable amount of fees for my transaction.',
        'sqd6' => 'SQD6: I feel the office was fair to everyone, or “walang palakasan”, during my transaction.',
        'sqd7' => 'SQD7: I was treated courteously by the staff, and (if asked for help) the staff was helpful.',
        'sqd8' => 'SQD8: I got what I needed from the government office, or denial of request was sufficiently explained to me.'
    ];
}
