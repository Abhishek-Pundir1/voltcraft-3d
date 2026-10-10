<?php
// ============================================================================
// VoltCraft 3D - Smart Electronics Diagnosis Rule-Based Engine
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Ensure appliance_diagnoses table exists idempotently
 */
function ensure_diagnosis_schema() {
    static $done = false;
    if ($done) return;

    $pdo = get_db_connection();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `appliance_diagnoses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_id` INT DEFAULT NULL,
            `customer_name` VARCHAR(120) DEFAULT NULL,
            `customer_phone` VARCHAR(40) DEFAULT NULL,
            `appliance_type` VARCHAR(100) NOT NULL,
            `brand` VARCHAR(100) NOT NULL,
            `model` VARCHAR(100) DEFAULT NULL,
            `problem_category` VARCHAR(100) NOT NULL,
            `symptoms` TEXT NOT NULL,
            `problem_description` TEXT NOT NULL,
            `troubleshooting_answers` JSON DEFAULT NULL,
            `possible_causes` JSON DEFAULT NULL,
            `safety_recommendations` JSON DEFAULT NULL,
            `media_attachment` VARCHAR(255) DEFAULT NULL,
            `service_request_id` INT DEFAULT NULL,
            `booking_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_diag_cust` (`customer_id`),
            INDEX `idx_diag_sr` (`service_request_id`),
            INDEX `idx_diag_bk` (`booking_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Try adding diagnosis_id to service_requests if not present
        try {
            $pdo->exec("ALTER TABLE `service_requests` ADD COLUMN `diagnosis_id` INT DEFAULT NULL AFTER `attachment`");
        } catch (PDOException $e) {
            // Column already exists
        }

        // Try adding diagnosis_id to bookings if not present
        try {
            $pdo->exec("ALTER TABLE `bookings` ADD COLUMN `diagnosis_id` INT DEFAULT NULL AFTER `service_id`");
        } catch (PDOException $e) {
            // Column already exists
        }

        $done = true;
    } catch (PDOException $e) {
        error_log("Failed to initialize diagnosis schema: " . $e->getMessage());
    }
}

// Ensure schema on include
ensure_diagnosis_schema();

/**
 * Get knowledge base of appliances, categories, and dynamic troubleshooting questions
 */
function get_diagnosis_knowledge_base() {
    return [
        'Television' => [
            'icon' => 'tv',
            'popular_brands' => ['Samsung', 'LG', 'Sony', 'Xiaomi / Mi', 'TCL', 'OnePlus', 'Panasonic', 'VU', 'Other'],
            'categories' => [
                'No Power / Won\'t Turn On' => [
                    'symptoms' => ['Completely dead', 'No standby LED', 'LED blinks continuously', 'Clicks repeatedly'],
                    'questions' => [
                        [
                            'id' => 'led_state',
                            'label' => 'What is the state of the power/standby indicator light?',
                            'options' => [
                                'Completely OFF (No light at all)' => 'led_off',
                                'Blinking / Flashing Red or Blue' => 'led_blinking',
                                'Solid ON (Red/Blue), but TV won\'t start' => 'led_solid',
                                'Flickers or dims when pressing power' => 'led_flicker'
                            ]
                        ],
                        [
                            'id' => 'sound_present',
                            'label' => 'Can you hear any audio or sound when pressing buttons?',
                            'options' => [
                                'No sound at all (Total silence)' => 'no_sound',
                                'Yes, normal TV sound / channel audio is audible' => 'sound_working',
                                'Faint clicking or relay humming from back' => 'relay_click'
                            ]
                        ],
                        [
                            'id' => 'tested_outlet',
                            'label' => 'Have you tested another working wall power outlet directly without extension strips?',
                            'options' => [
                                'Yes, tested directly on a confirmed working outlet' => 'tested_outlet_yes',
                                'Not yet tested directly' => 'tested_outlet_no'
                            ]
                        ],
                        [
                            'id' => 'sudden_stop',
                            'label' => 'Did the television stop working suddenly after a power outage or storm?',
                            'options' => [
                                'Yes, happened after power fluctuation or storm' => 'surge_event_yes',
                                'No, was working normally and failed upon next startup' => 'surge_event_no',
                                'Uncertain' => 'surge_event_unsure'
                            ]
                        ]
                    ]
                ],
                'Black Screen / No Display (Sound Works)' => [
                    'symptoms' => ['Sound is working', 'Black screen', 'Faint shadow visible', 'Backlight bleed'],
                    'questions' => [
                        [
                            'id' => 'flashlight_test',
                            'label' => 'Flashlight Test: If you shine your mobile flashlight 2 inches away from the dark screen, do you see faint outlines of the menu or picture?',
                            'options' => [
                                'Yes! Faint ghost image/menu is visible under torch' => 'flashlight_positive',
                                'No, screen is pitch black everywhere' => 'flashlight_negative',
                                'Screen glows dimly with blue/gray hue' => 'screen_dim_glow'
                            ]
                        ],
                        [
                            'id' => 'menu_button',
                            'label' => 'Does pressing the physical or remote MENU button bring up any on-screen display?',
                            'options' => [
                                'No on-screen display at all' => 'no_osd',
                                'Brief flash of logo then darkness' => 'flash_logo'
                            ]
                        ]
                    ]
                ],
                'Lines on Screen / Display Artifacts' => [
                    'symptoms' => ['Vertical colored lines', 'Horizontal black bars', 'Flickering image', 'Double image'],
                    'questions' => [
                        [
                            'id' => 'line_orientation',
                            'label' => 'What type of display distortion is appearing?',
                            'options' => [
                                'Thin vertical colored lines spanning top to bottom' => 'vertical_lines',
                                'Horizontal black bands across screen' => 'horizontal_bands',
                                'Screen jumping / double vertical image' => 'double_image',
                                'Spiderweb crack / internal glass rupture' => 'cracked_panel'
                            ]
                        ]
                    ]
                ],
                'Audio Faults / Distorted Sound' => [
                    'symptoms' => ['No sound', 'Buzzing audio', 'Cracking speaker', 'Muffled dialogue'],
                    'questions' => [
                        [
                            'id' => 'external_audio',
                            'label' => 'Do external headphones or soundbar produce clear audio via HDMI / AUX?',
                            'options' => [
                                'Yes, external audio works fine' => 'ext_audio_works',
                                'No, external audio also silent or distorted' => 'ext_audio_fails'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Air Conditioner' => [
            'icon' => 'wind',
            'popular_brands' => ['Daikin', 'Voltas', 'LG', 'Mitsubishi', 'Hitachi', 'Carrier', 'Blue Star', 'Lloyd', 'Other'],
            'categories' => [
                'Not Cooling / Blowing Warm Air' => [
                    'symptoms' => ['Fan blowing warm air', 'Takes too long to cool', 'Compressor doesn\'t kick in', 'Weak airflow'],
                    'questions' => [
                        [
                            'id' => 'outdoor_running',
                            'label' => 'Is the outdoor condenser unit running (fan spinning & compressor humming)?',
                            'options' => [
                                'Both outdoor fan and compressor are running' => 'outdoor_both_running',
                                'Outdoor fan spins, but compressor is silent / buzzing' => 'outdoor_fan_only',
                                'Outdoor unit is completely silent' => 'outdoor_totally_off',
                                'Not accessible to verify' => 'outdoor_not_accessible'
                            ]
                        ],
                        [
                            'id' => 'filter_clean',
                            'label' => 'When were the indoor nylon air mesh filters last washed?',
                            'options' => [
                                'Cleaned within the last 2 weeks' => 'filters_clean',
                                'Dirty / Clogged with dust or not checked in months' => 'filters_dirty'
                            ]
                        ],
                        [
                            'id' => 'ice_formation',
                            'label' => 'Is there visible ice or frost on the indoor cooling coil or thin outdoor copper pipes?',
                            'options' => [
                                'Yes, visible ice layer or frost on pipes/coils' => 'ice_present',
                                'No ice or frost observed' => 'ice_absent'
                            ]
                        ]
                    ]
                ],
                'Water Leaking Inside Room' => [
                    'symptoms' => ['Water dripping down wall', 'Puddle beneath indoor unit', 'Gurgling sound', 'Ice spitting'],
                    'questions' => [
                        [
                            'id' => 'leak_drain_flow',
                            'label' => 'Is water actively exiting the exterior outdoor drain pipe when AC is running?',
                            'options' => [
                                'No water exiting outdoor pipe at all' => 'drain_blocked',
                                'Yes, water exits outside, but also drips inside' => 'drain_partial',
                                'Outdoor drain pipe is blocked or kinked' => 'drain_kinked'
                            ]
                        ]
                    ]
                ],
                'AC Tripping Breaker / Won\'t Turn On' => [
                    'symptoms' => ['Trips MCB immediately', 'Remote doesn\'t respond', 'Burnt electrical smell'],
                    'questions' => [
                        [
                            'id' => 'mcb_timing',
                            'label' => 'When exactly does the circuit breaker (MCB) trip?',
                            'options' => [
                                'Instantly upon switching power switch ON' => 'trip_instant',
                                'After 3-5 minutes when compressor tries to engage' => 'trip_compressor_start',
                                'Randomly after running for 30+ minutes' => 'trip_delayed'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Refrigerator' => [
            'icon' => 'thermometer-snowflake',
            'popular_brands' => ['LG', 'Samsung', 'Whirlpool', 'Haier', 'Godrej', 'Bosch', 'Panasonic', 'Voltas Beko', 'Other'],
            'categories' => [
                'Freezer Cold but Fridge Warm' => [
                    'symptoms' => ['Freezer makes ice, bottom fridge warm', 'Milk spoils in fridge', 'Frost buildup on back wall'],
                    'questions' => [
                        [
                            'id' => 'airflow_vent',
                            'label' => 'Can you feel cold air blowing from the vent inside the fresh food compartment?',
                            'options' => [
                                'No airflow feeling inside bottom fridge' => 'no_lower_airflow',
                                'Weak airflow present' => 'weak_lower_airflow',
                                'Airflow seems normal but temperature is warm' => 'normal_airflow_warm'
                            ]
                        ],
                        [
                            'id' => 'frost_buildup',
                            'label' => 'Is there excessive thick snow/frost on the freezer back wall panel?',
                            'options' => [
                                'Yes, thick white frost or ice covering the rear panel' => 'freezer_heavy_frost',
                                'No, clean and frost-free' => 'freezer_no_frost'
                            ]
                        ]
                    ]
                ],
                'Not Cooling at All (Clicks or Silent)' => [
                    'symptoms' => ['Everything melting', 'Clicking sound from compressor', 'Interior light works but no cooling'],
                    'questions' => [
                        [
                            'id' => 'compressor_sound',
                            'label' => 'Do you hear a regular "click" sound every 2 to 3 minutes from the lower back of the fridge?',
                            'options' => [
                                'Yes, audible click every few minutes, then silence' => 'compressor_click',
                                'Continuous normal low hum, but no cooling' => 'compressor_runs_no_cool',
                                'Total silence, compressor completely dead and cold' => 'compressor_dead'
                            ]
                        ],
                        [
                            'id' => 'door_seal',
                            'label' => 'Are the rubber door gaskets sealing firmly all around without tears or gaps?',
                            'options' => [
                                'Gaskets seal firmly on all sides' => 'seal_good',
                                'Gaskets are loose, warped, or cracked' => 'seal_damaged'
                            ]
                        ]
                    ]
                ],
                'Water Leaking on Floor' => [
                    'symptoms' => ['Water pooling under vegetable tray', 'Puddle on kitchen floor', 'Water dripping from bottom'],
                    'questions' => [
                        [
                            'id' => 'water_location',
                            'label' => 'Where is the water predominantly gathering?',
                            'options' => [
                                'Inside the fridge under the vegetable crisper drawers' => 'leak_inside_crisper',
                                'Outside on the kitchen floor underneath or behind the unit' => 'leak_floor_back'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Washing Machine' => [
            'icon' => 'disc',
            'popular_brands' => ['IFB', 'LG', 'Samsung', 'Bosch', 'Whirlpool', 'Godrej', 'Haier', 'Panasonic', 'Other'],
            'categories' => [
                'Won\'t Drain Water / Error Code (E2, OE, 5E)' => [
                    'symptoms' => ['Water trapped in drum', 'Error code on display', 'Drain pump humming but water remains'],
                    'questions' => [
                        [
                            'id' => 'drain_filter_checked',
                            'label' => 'Have you opened and cleaned the front-bottom coin/debris filter trap?',
                            'options' => [
                                'Yes, checked and clear of coins/pins' => 'filter_cleared',
                                'Not checked / Haven\'t opened it yet' => 'filter_not_checked',
                                'Found debris/coins and cleaned it, still won\'t drain' => 'filter_cleaned_persists'
                            ]
                        ],
                        [
                            'id' => 'pump_humming',
                            'label' => 'Can you hear a humming or straining motor sound when machine reaches drain cycle?',
                            'options' => [
                                'Yes, motor hums but water doesn\'t drain' => 'pump_sound_present',
                                'Complete silence during drain cycle' => 'pump_silent'
                            ]
                        ]
                    ]
                ],
                'Won\'t Spin / Drum Doesn\'t Rotate' => [
                    'symptoms' => ['Fills and drains but doesn\'t spin', 'Clothes soaking wet', 'Belt slipping sound'],
                    'questions' => [
                        [
                            'id' => 'drum_manual_turn',
                            'label' => 'When unplugged, does the inner metal drum rotate smoothly by hand, or is it stiff/jammed?',
                            'options' => [
                                'Rotates freely and smoothly by hand' => 'drum_turns_free',
                                'Very stiff or completely jammed/locked' => 'drum_stiff_locked',
                                'Rotates with loud scraping / grinding noise' => 'drum_grinding_bearing'
                            ]
                        ]
                    ]
                ],
                'Heavy Shaking / Violent Banging Noise' => [
                    'symptoms' => ['Machine walks during spin cycle', 'Loud banging noise', 'Drum wobbles excessively'],
                    'questions' => [
                        [
                            'id' => 'machine_level',
                            'label' => 'Is the machine sitting completely level on firm floor without rocking on its feet?',
                            'options' => [
                                'Firmly leveled on all 4 rubber feet' => 'level_confirmed',
                                'Rocks or wobbles when pushed lightly' => 'wobbles_unlevel'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Microwave' => [
            'icon' => 'zap',
            'popular_brands' => ['LG', 'Samsung', 'IFB', 'Panasonic', 'Bajaj', 'Morphy Richards', 'Whirlpool', 'Godrej', 'Other'],
            'categories' => [
                'Runs and Turns but Does Not Heat Food' => [
                    'symptoms' => ['Food stays stone cold', 'Turntable and timer work', 'Unusual hum/buzz during heating'],
                    'questions' => [
                        [
                            'id' => 'all_functions_work',
                            'label' => 'Does the digital display, interior light, and turntable rotate normally while remaining cold?',
                            'options' => [
                                'Yes, everything looks normal but food doesn\'t warm at all' => 'cold_normal_display',
                                'Loud deeper buzzing sound accompanies heating attempt' => 'cold_loud_buzz'
                            ]
                        ]
                    ]
                ],
                'Sparks or Arcing Inside Cavity' => [
                    'symptoms' => ['Bright flashes inside oven', 'Cracking popping noise', 'Burnt smell'],
                    'questions' => [
                        [
                            'id' => 'waveguide_cover',
                            'label' => 'Inspect the small cardboard/mica cover on the inside right wall. Is it greasy, burned, or hole-punctured?',
                            'options' => [
                                'Yes, mica cover has dark scorch marks or hole' => 'mica_burned',
                                'Mica cover looks clean and intact' => 'mica_clean',
                                'Sparks only happened when metal foil/utensil was inside' => 'metal_utensil_spark'
                            ]
                        ]
                    ]
                ],
                'Touchpad / Keypad Buttons Unresponsive' => [
                    'symptoms' => ['Buttons don\'t beep', 'Start button unresponsive', 'Child lock icon displayed'],
                    'questions' => [
                        [
                            'id' => 'child_lock_active',
                            'label' => 'Is there an "L" or lock icon on the display (Child Lock feature)?',
                            'options' => [
                                'Yes, lock indicator is visible' => 'child_lock_on',
                                'No lock icon, buttons simply fail to register' => 'keypad_membrane_failed'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Inverter and UPS' => [
            'icon' => 'battery-charging',
            'popular_brands' => ['Luminous', 'Microtek', 'Exide', 'Livguard', 'Amaron', 'V-Guard', 'Su-Kam', 'Other'],
            'categories' => [
                'Continuous Overload Alarm Beep' => [
                    'symptoms' => ['Beeps continuously', 'Red overload LED illuminated', 'Cuts output power'],
                    'questions' => [
                        [
                            'id' => 'load_disconnected',
                            'label' => 'If you disconnect all heavy household appliances (refrigerator, motor) from the inverter output, does the alarm stop?',
                            'options' => [
                                'Alarm stops once heavy load is unplugged' => 'load_shed_stops',
                                'Alarm beeps continuously even with zero load plugged in' => 'overload_zero_load'
                            ]
                        ]
                    ]
                ],
                'Low Battery / Immediate Cutoff on Power Failure' => [
                    'symptoms' => ['Cuts off immediately during blackout', 'Low battery alarm within 2 minutes', 'Battery doesn\'t hold charge'],
                    'questions' => [
                        [
                            'id' => 'battery_age',
                            'label' => 'How old is the connected inverter battery?',
                            'options' => [
                                'Less than 18 months old' => 'battery_fresh',
                                'Between 2 and 4 years old' => 'battery_mature',
                                'Older than 4 years' => 'battery_aged'
                            ]
                        ],
                        [
                            'id' => 'terminal_corrosion',
                            'label' => 'Is there white/blue powdery sulfate corrosion crust on the lead battery terminals?',
                            'options' => [
                                'Heavy corrosion crust visible on posts' => 'terminals_corroded',
                                'Terminals are clean and tightly secured' => 'terminals_clean'
                            ]
                        ]
                    ]
                ],
                'Won\'t Switch to Grid / Charging Fails' => [
                    'symptoms' => ['Mains LED won\'t turn on', 'Charging LED doesn\'t glow', 'Stuck in battery backup mode'],
                    'questions' => [
                        [
                            'id' => 'mains_fuse',
                            'label' => 'Have you checked the small AC input fuse or reset pin on the back panel of the inverter?',
                            'options' => [
                                'Fuse is intact / reset pin clicked' => 'fuse_checked',
                                'Haven\'t checked input fuse' => 'fuse_not_checked'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'Other electronic devices' => [
            'icon' => 'cpu',
            'popular_brands' => ['Apple', 'Dell', 'HP', 'Lenovo', 'Asus', 'Sony', 'Generic Electronics', 'Other'],
            'categories' => [
                'General Power Failure' => [
                    'symptoms' => ['Won\'t turn on', 'No LED response', 'Overheating adapter', 'Component smoke'],
                    'questions' => [
                        [
                            'id' => 'liquid_spill',
                            'label' => 'Was the device exposed to moisture, liquid spillage, or a physical drop?',
                            'options' => [
                                'Yes, liquid spillage or moisture contact' => 'liquid_exposure',
                                'Yes, physical drop or impact' => 'physical_drop',
                                'No external damage, failed under normal use' => 'normal_use_failure'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Execute the Rule-Based Diagnosis Analysis
 * Returns probable causes, safe troubleshooting suggestions, and safety warnings
 */
function run_rule_based_diagnosis($appliance, $category, $answers = []) {
    $results = [
        'appliance' => $appliance,
        'category' => $category,
        'summary_headline' => '',
        'probable_causes' => [],
        'safe_troubleshooting' => [],
        'safety_warnings' => [],
        'estimated_labor_hours' => '1 - 3 Hours',
        'recommended_specialist' => 'Certified Electronics Engineer'
    ];

    // UNIVERSAL SAFETY MANDATE (Strict rule requirement #7)
    $results['safety_warnings'][] = 'CRITICAL SAFETY DIRECTIVE: Under NO circumstances should you open electrical casings, unscrew chassis panels, or attempt live internal wire probing. High-voltage capacitors, transformers, and switch-mode power supplies retain lethal electric charges even when unplugged from the wall.';
    $results['safety_warnings'][] = 'PRELIMINARY ASSESSMENT NOTICE: This diagnostic analysis is algorithmic and preliminary based on reported behavioral symptoms. An on-site certified engineer must perform physical hardware measurements with multimeters and oscilloscopes to confirm the exact failing component.';

    // 1. TELEVISION RULES
    if ($appliance === 'Television') {
        $results['recommended_specialist'] = 'Display & Micro-Soldering Hardware Specialist';

        if ($category === 'No Power / Won\'t Turn On') {
            $led = $answers['led_state'] ?? '';
            $outlet = $answers['tested_outlet'] ?? '';
            $sound = $answers['sound_present'] ?? '';

            if ($outlet === 'tested_outlet_no') {
                $results['safe_troubleshooting'][] = 'Disconnect the TV power plug, connect a working portable lamp or phone charger to the same socket to verify wall power, and try a different direct wall outlet.';
            }

            if ($led === 'led_blinking') {
                $results['summary_headline'] = 'Power Supply Unit (SMPS) Overvoltage Fault or Motherboard Standby Lock';
                $results['probable_causes'] = [
                    ['cause' => 'SMPS Power Supply Protection Circuit Tripped (Shorted MOSFET or Filter Capacitor)', 'probability' => '85%'],
                    ['cause' => 'Mainboard Firmware Standby Handshake Failure', 'probability' => '65%'],
                    ['cause' => 'Defective Standby Power IC Regulator', 'probability' => '40%']
                ];
                $results['safe_troubleshooting'][] = 'Perform a Cold Power Drain: Unplug the TV from the wall, disconnect all HDMI/USB cables, hold down the physical power button on the TV chassis for 30 seconds, wait 15 minutes, and reconnect directly.';
            } elseif ($led === 'led_off') {
                $results['summary_headline'] = 'Primary Power Delivery Defect or Blown Bridge Rectifier';
                $results['probable_causes'] = [
                    ['cause' => 'Blown Internal Slow-Blow Glass/Ceramic Fuse on Power Board', 'probability' => '90%'],
                    ['cause' => 'Primary Switching Transistor Short-Circuit', 'probability' => '75%'],
                    ['cause' => 'Damaged AC Power Cord or Socket Connector Pin', 'probability' => '30%']
                ];
                $results['safe_troubleshooting'][] = 'Inspect the detachable power cord for any visible pinches or burns. Ensure it is firmly seated in the rear AC inlet.';
            } else {
                $results['summary_headline'] = 'Logic Board Standby Controller Defect';
                $results['probable_causes'] = [
                    ['cause' => 'Main Logic Board Processing Chip Failure', 'probability' => '70%'],
                    ['cause' => 'Defective Power Supply Relay Switching Coil', 'probability' => '50%']
                ];
                $results['safe_troubleshooting'][] = 'Remove batteries from remote for 2 minutes to eliminate stuck remote signals, and use physical chassis power button.';
            }
        } elseif ($category === 'Black Screen / No Display (Sound Works)') {
            $flashlight = $answers['flashlight_test'] ?? '';
            if ($flashlight === 'flashlight_positive') {
                $results['summary_headline'] = 'LED Backlight Strip Array or Backlight Driver Board Failure';
                $results['probable_causes'] = [
                    ['cause' => 'Burned Out LED Backlight Strip Array (Edge-Lit / Direct-Lit)', 'probability' => '95%'],
                    ['cause' => 'High-Voltage Constant Current LED Driver Circuit Malfunction', 'probability' => '80%']
                ];
                $results['safe_troubleshooting'][] = 'Your LCD display panel and main audio processing are functioning normally. Turn off the TV to prevent overheating the remaining LED driver circuits. Engineer will need to replace the LED light strips.';
            } else {
                $results['summary_headline'] = 'T-CON (Timing Controller) Board or Panel LVDS Ribbon Defect';
                $results['probable_causes'] = [
                    ['cause' => 'Defective T-CON Board PMIC Voltage Converter', 'probability' => '80%'],
                    ['cause' => 'Loose or Oxidized LVDS Ribbon Cable Connection', 'probability' => '50%']
                ];
                $results['safe_troubleshooting'][] = 'Verify all video source inputs (Set-Top Box, HDMI cable). Unplug external HDMI cables and plug back in.';
            }
        } else {
            $results['summary_headline'] = 'Display Matrix or COF Bonding Driver Degradation';
            $results['probable_causes'] = [
                ['cause' => 'Chip-On-Film (COF) Flex Ribbon IC Overheating / Debonding', 'probability' => '75%'],
                ['cause' => 'T-CON Board Gamma Voltage Distortion', 'probability' => '60%']
            ];
            $results['safe_troubleshooting'][] = 'Avoid pressing or squeezing the edges of the TV panel, as this can worsen ribbon cable detachment.';
        }
    }

    // 2. AIR CONDITIONER RULES
    elseif ($appliance === 'Air Conditioner') {
        $results['recommended_specialist'] = 'HVAC & Inverter Compressor Electronics Engineer';

        if ($category === 'Not Cooling / Blowing Warm Air') {
            $outdoor = $answers['outdoor_running'] ?? '';
            $filter = $answers['filter_clean'] ?? '';
            $ice = $answers['ice_formation'] ?? '';

            if ($ice === 'ice_present') {
                $results['summary_headline'] = 'Refrigerant Depletion (Gas Leak) or Extreme Airflow Restriction';
                $results['probable_causes'] = [
                    ['cause' => 'Refrigerant Leakage (R32 / R410A Pressure Drop)', 'probability' => '90%'],
                    ['cause' => 'Heavily Clogged Evaporator Coils or Blocked Capillary Expansion Valve', 'probability' => '75%']
                ];
                $results['safe_troubleshooting'][] = 'Turn AC system to FAN-ONLY mode for 1 hour to allow accumulated ice to thaw naturally into the drain pan without stressing the compressor.';
            } elseif ($outdoor === 'outdoor_fan_only') {
                $results['summary_headline'] = 'Compressor Dual Run Capacitor Failure or Inverter IPM Module Fault';
                $results['probable_causes'] = [
                    ['cause' => 'Failed Dual Run Capacitor (Microfarad Value Depleted)', 'probability' => '90%'],
                    ['cause' => 'Overheating Compressor Thermal Overload Protector (TOP)', 'probability' => '60%']
                ];
                $results['safe_troubleshooting'][] = 'Turn AC breaker OFF. Running the outdoor fan while compressor is locked can overheat compressor motor windings.';
            } else {
                $results['summary_headline'] = 'Thermostat Sensor Calibration or Outdoor Inverter Communication Error';
                $results['probable_causes'] = [
                    ['cause' => 'Faulty Room Ambient / Coil Thermistor Sensor', 'probability' => '70%'],
                    ['cause' => 'Clogged Indoor Nylon Mesh Filter Screens', 'probability' => '60%']
                ];
                $results['safe_troubleshooting'][] = 'Wash removable nylon indoor filters under lukewarm tap water, let them dry completely before reinserting. Ensure remote is in COOL mode and set at 22-24°C.';
            }
        } elseif ($category === 'Water Leaking Inside Room') {
            $results['summary_headline'] = 'Condensate Drain Pipe Blockage or Drain Trough Slime Build-up';
            $results['probable_causes'] = [
                ['cause' => 'Algae / Dust Sludge Blocking the Condensate Gravity Drain Line', 'probability' => '90%'],
                ['cause' => 'Dislocated or Cracked Indoor Drain Collector Tray', 'probability' => '65%']
            ];
            $results['safe_troubleshooting'][] = 'Turn off AC immediately to prevent water damage to drywall and electronics. Inspect the outdoor drain hose outlet to verify if dirt or a bend is impeding gravity water flow.';
        } else {
            $results['summary_headline'] = 'Compressor Motor Winding Short to Ground or Inverter PCB IPM Failure';
            $results['probable_causes'] = [
                ['cause' => 'Grounded Compressor Motor Winding Insulation Breakdown', 'probability' => '85%'],
                ['cause' => 'Outdoor Inverter Power Board IPM Transistor Bridge Short', 'probability' => '75%']
            ];
            $results['safe_troubleshooting'][] = 'Leave the circuit breaker OFF. Do NOT repeatedly force-reset the breaker as this can cause permanent electrical fire hazard.';
        }
    }

    // 3. REFRIGERATOR RULES
    elseif ($appliance === 'Refrigerator') {
        $results['recommended_specialist'] = 'Domestic Refrigeration & Sealed-System Specialist';

        if ($category === 'Not Cooling at All (Clicks or Silent)') {
            $comp_sound = $answers['compressor_sound'] ?? '';
            if ($comp_sound === 'compressor_click') {
                $results['summary_headline'] = 'PTC Starter Relay or Overload Protector (OLP) Defect';
                $results['probable_causes'] = [
                    ['cause' => 'Cracked or Blown PTC Ceramic Starter Relay Disc', 'probability' => '95%'],
                    ['cause' => 'Weak Start Capacitor on Compressor Terminal', 'probability' => '70%'],
                    ['cause' => 'Mechanically Locked Compressor Piston / Stator', 'probability' => '45%']
                ];
                $results['safe_troubleshooting'][] = 'Unplug the refrigerator from the wall to prevent compressor motor burnout caused by repetitive unsuccessful start attempts.';
            } else {
                $results['summary_headline'] = 'Main Control Board Relay or Thermostat Temperature Controller Defect';
                $results['probable_causes'] = [
                    ['cause' => 'Failed Inverter Driver Board or Temperature Thermostat', 'probability' => '80%'],
                    ['cause' => 'Refrigerant Loss (Puncture or Corrosion Leak)', 'probability' => '65%']
                ];
                $results['safe_troubleshooting'][] = 'Check that interior temperature dials are not set to minimum or holiday mode.';
            }
        } elseif ($category === 'Freezer Cold but Fridge Warm') {
            $results['summary_headline'] = 'Automatic Defrost System Malfunction (Heater, Sensor, or Bi-Metal)';
            $results['probable_causes'] = [
                ['cause' => 'Blown Defrost Heating Element or Blown Thermal Fuse', 'probability' => '85%'],
                ['cause' => 'Defective Defrost Bi-Metal Thermostat Sensor', 'probability' => '75%'],
                ['cause' => 'Evaporator Fan Motor Stuck / Frozen in Ice', 'probability' => '60%']
            ];
            $results['safe_troubleshooting'][] = 'Ensure air vents between the freezer and refrigerator sections are not blocked by large food packages.';
        } else {
            $results['summary_headline'] = 'Clogged Defrost Drain Gutter / Ice Plug';
            $results['probable_causes'] = [
                ['cause' => 'Frozen or Clogged Drain Hole Under Evaporator Coil', 'probability' => '90%'],
                ['cause' => 'Cracked Evaporation Pan on Top of Compressor', 'probability' => '50%']
            ];
            $results['safe_troubleshooting'][] = 'Wipe up interior water to prevent food contamination. A technician will flush the internal drain line with hot solution.';
        }
    }

    // 4. WASHING MACHINE RULES
    elseif ($appliance === 'Washing Machine') {
        $results['recommended_specialist'] = 'Appliance Electromechanical & Motor Specialist';

        if ($category === 'Won\'t Drain Water / Error Code (E2, OE, 5E)') {
            $filter = $answers['drain_filter_checked'] ?? '';
            $results['summary_headline'] = 'Drain Pump Impeller Jam or Blocked Foreign Debris Trap';
            $results['probable_causes'] = [
                ['cause' => 'Foreign Object (Coin, Pin, Bra Wire) Jamming Drain Impeller', 'probability' => '85%'],
                ['cause' => 'Burned Out Drain Pump Synchronous Motor Coil', 'probability' => '70%'],
                ['cause' => 'Clogged Pressure Switch / Water Level Sensor Tube', 'probability' => '45%']
            ];

            if ($filter === 'filter_not_checked') {
                $results['safe_troubleshooting'][] = 'SAFE USER CHECK: Unplug machine, place a shallow tray and towel beneath the small door at the bottom-front, carefully unscrew the round coin trap filter, and clear any trapped coins or lint.';
            } else {
                $results['safe_troubleshooting'][] = 'Check that the flexible ribbed drain pipe at the back of the washer is not kinked or elevated higher than 1 meter above floor level.';
            }
        } elseif ($category === 'Won\'t Spin / Drum Doesn\'t Rotate') {
            $drum = $answers['drum_manual_turn'] ?? '';
            if ($drum === 'drum_grinding_bearing') {
                $results['summary_headline'] = 'Drum Tub Bearing & Shaft Seal Wear Out';
                $results['probable_causes'] = [
                    ['cause' => 'Water Ingress Damaged Internal Tub Bearings & Spider Arm', 'probability' => '95%']
                ];
                $results['safe_troubleshooting'][] = 'Do not attempt to run spin cycle as drum could strike outer tub.';
            } else {
                $results['summary_headline'] = 'Drive Belt Slippage or Motor Run Capacitor / Carbon Brushes Depleted';
                $results['probable_causes'] = [
                    ['cause' => 'Snapped or Slipped Poly-V Motor Drive Belt', 'probability' => '85%'],
                    ['cause' => 'Worn Motor Carbon Brushes or Motor Control Board Relay', 'probability' => '70%']
                ];
                $results['safe_troubleshooting'][] = 'Verify that the wash load is evenly distributed inside the drum.';
            }
        } else {
            $results['summary_headline'] = 'Worn Suspension Dampers or Unbalanced Load';
            $results['probable_causes'] = [
                ['cause' => 'Worn Friction Shock Absorber Dampers / Broken Support Springs', 'probability' => '85%'],
                ['cause' => 'Uneven Leg Leveling or Broken Counterweight Block', 'probability' => '65%']
            ];
            $results['safe_troubleshooting'][] = 'Ensure machine rests on a rigid non-slippery floor. Check each corner foot with a level.';
        }
    }

    // 5. MICROWAVE RULES
    elseif ($appliance === 'Microwave') {
        $results['recommended_specialist'] = 'High-Voltage Microwave & RF Electronics Specialist';

        // STRICT MANDATORY MICROWAVE SAFETY WARNING
        $results['safety_warnings'][] = '⚠️ EXTREME HAZARD WARNING: Microwave ovens operate at 2,000 to 4,000 Volts. The high-voltage capacitor stores lethal electric energy even days after the machine is unplugged. Never remove the external cover.';

        if ($category === 'Runs and Turns but Does Not Heat Food') {
            $results['summary_headline'] = 'High-Voltage Circuit Failure (Magnetron, HV Diode, or HV Fuse)';
            $results['probable_causes'] = [
                ['cause' => 'Defective Magnetron Tube (Filament Open or Vacuum Lost)', 'probability' => '85%'],
                ['cause' => 'Blown High-Voltage In-Line Protection Fuse (5kV 0.8A)', 'probability' => '80%'],
                ['cause' => 'Shorted High-Voltage Rectifier Diode', 'probability' => '65%']
            ];
            $results['safe_troubleshooting'][] = 'Do not run the microwave empty. Unplug the microwave from the outlet. An engineer must test the HV components inside a protected environment.';
        } elseif ($category === 'Sparks or Arcing Inside Cavity') {
            $results['summary_headline'] = 'Burnt Waveguide Mica Sheet or Cavity Arc Damage';
            $results['probable_causes'] = [
                ['cause' => 'Carbonized Food Splatter on Mica Waveguide Cover Plate', 'probability' => '90%'],
                ['cause' => 'Chipped Interior Cavity Paint Exposing Bare Metal', 'probability' => '70%']
            ];
            $results['safe_troubleshooting'][] = 'Inspect the interior right-side mica cardboard sheet. Clean any food grease splatters with a damp soapy cloth and let dry completely. Never use metal utensils or bowls.';
        } else {
            $results['summary_headline'] = 'Membrane Switch Keypad Ribbon Failure or Child Lock Active';
            $results['probable_causes'] = [
                ['cause' => 'Conductive Trace Oxidation in Touch Membrane Keypad', 'probability' => '80%'],
                ['cause' => 'Digital Microcontroller Board Power Supply Fault', 'probability' => '60%']
            ];
            $results['safe_troubleshooting'][] = 'Press and hold the STOP / CANCEL button for 5 seconds to deactivate the Child Lock function.';
        }
    }

    // 6. INVERTER AND UPS RULES
    elseif ($appliance === 'Inverter and UPS') {
        $results['recommended_specialist'] = 'Power Electronics & Battery Systems Engineer';

        if ($category === 'Low Battery / Immediate Cutoff on Power Failure') {
            $age = $answers['battery_age'] ?? '';
            $terminals = $answers['terminal_corrosion'] ?? '';

            $results['summary_headline'] = 'Lead-Acid Battery Sulfation or Terminal Voltage Collapse under Load';
            $results['probable_causes'] = [
                ['cause' => 'Battery Cell Degradation / Internal Plate Sulfation', 'probability' => ($age === 'battery_aged' ? '95%' : '75%')],
                ['cause' => 'High Resistance Oxide Layer on Battery Terminal Posts', 'probability' => ($terminals === 'terminals_corroded' ? '90%' : '50%')],
                ['cause' => 'Inverter Charging Transformer Circuit Dropout', 'probability' => '60%']
            ];

            if ($terminals === 'terminals_corroded') {
                $results['safe_troubleshooting'][] = 'SAFE MAINTENANCE: Turn off inverter switch, unplug AC input. Pour warm water mixed with baking soda over the outer terminal posts to neutralize sulfate crust, dry completely, and apply petroleum jelly.';
            } else {
                $results['safe_troubleshooting'][] = 'Check electrolyte distilled water level float indicators on tubular batteries and top up with distilled water if floats are low.';
            }
        } elseif ($category === 'Continuous Overload Alarm Beep') {
            $results['summary_headline'] = 'Power Inverter MOSFET H-Bridge Transistor Short';
            $results['probable_causes'] = [
                ['cause' => 'Blown Power MOSFET Transistor Bank', 'probability' => '90%'],
                ['cause' => 'Faulty Output Current Sensing Circuit (CT Shunt)', 'probability' => '75%']
            ];
            $results['safe_troubleshooting'][] = 'Switch off inverter front power button, unplug the 3-pin output plug from the back of the inverter, and restart. If alarm continues with zero load, MOSFET board repair is needed.';
        } else {
            $results['summary_headline'] = 'Mains Sense Optical Coupler or Input Glass Fuse Blown';
            $results['probable_causes'] = [
                ['cause' => 'Blown AC Input Glass Fuse on Rear Inverter Panel', 'probability' => '85%'],
                ['cause' => 'Defective Heavy-Duty Changeover Relay', 'probability' => '75%']
            ];
            $results['safe_troubleshooting'][] = 'Check the small glass fuse socket on the back panel of the inverter. If wire inside glass is broken, replace with exact rating fuse (usually 10A / 16A).';
        }
    }

    // 7. OTHER ELECTRONICS
    else {
        $results['recommended_specialist'] = 'Micro-Soldering & Circuit Diagnostics Specialist';
        $results['summary_headline'] = 'Component-Level Circuit Board Failure or Power Rail Short';
        $results['probable_causes'] = [
            ['cause' => 'SMD Ceramic Capacitor Short to Ground on Main Voltage Rail', 'probability' => '80%'],
            ['cause' => 'Power Management IC (PMIC) Internal Failure', 'probability' => '65%'],
            ['cause' => 'Cold Solder Joint or Oxidized Ribbon Connector', 'probability' => '50%']
        ];
        $results['safe_troubleshooting'][] = 'Disconnect all power sources and battery packs immediately. Do not attempt to recharge if liquid contact occurred.';
    }

    return $results;
}

/**
 * Save customer diagnosis into database
 */
function save_appliance_diagnosis($data) {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("INSERT INTO appliance_diagnoses 
        (customer_id, customer_name, customer_phone, appliance_type, brand, model, 
         problem_category, symptoms, problem_description, troubleshooting_answers, 
         possible_causes, safety_recommendations, media_attachment)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $data['customer_id'] ?? null,
        $data['customer_name'] ?? 'Guest Customer',
        $data['customer_phone'] ?? null,
        $data['appliance_type'],
        $data['brand'],
        $data['model'] ?? null,
        $data['problem_category'],
        is_array($data['symptoms']) ? implode(', ', $data['symptoms']) : $data['symptoms'],
        $data['problem_description'],
        json_encode($data['troubleshooting_answers'] ?? []),
        json_encode($data['possible_causes'] ?? []),
        json_encode($data['safety_recommendations'] ?? []),
        $data['media_attachment'] ?? null
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Fetch diagnosis by ID, Request ID, or Booking ID
 */
function get_diagnosis_dossier($diagnosis_id = null, $request_id = null, $booking_id = null) {
    $pdo = get_db_connection();

    if ($diagnosis_id) {
        $stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE id = ?");
        $stmt->execute([$diagnosis_id]);
        return $stmt->fetch();
    } elseif ($request_id) {
        $stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE service_request_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$request_id]);
        return $stmt->fetch();
    } elseif ($booking_id) {
        $stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$booking_id]);
        return $stmt->fetch();
    }
    return null;
}
