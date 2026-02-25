<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = [
            // ['100561', 'Correa', 'Edwin', 'Bautista', '', 'EDWIN'],
            ['4422136', 'Mosqueda', 'Louise', 'Peñaranda', '', 'LOUISE'],
            ['4420420', 'Dela Paz', 'Joana Leonisa', 'Pielago', '', 'JOANA'],
            ['101046', 'Rayos', 'Raoul Enrico', 'Victoria', '', 'ERICK'],
        ];

        $rsp = [
            // ['100325', 'Rosas', 'Minerva', 'Villanueva', '', 'MINNIE'],
            ['4411012', 'Andrada', 'Jarbel', 'Barria', '', 'JABBY'],
            ['4412085', 'Romualdo', 'Katherine Joy', 'Angeles', '', 'KATH'],
            ['100673', 'Avis', 'Felicitas', 'Sumatra', '', 'JIGS'],
            ['4420840', 'Azusano', 'Irish May', 'Cabillan', '', 'IRISH'],
            ['4418477', 'Bato', 'Luzviminda', 'Eser', '', 'LUZ'],
            ['4419049', 'Caeg', 'Janelle', 'Caña', '', 'JANELLE'],
            ['4410245', 'Gacutan', 'Rovina', 'Evangelista', '', 'ROVI'],
            ['4416294', 'Hernandez', 'Denise Allison', 'Reyes', '', 'DENISE'],
            ['4418318', 'Labis', 'Axl Rose', 'Araman', '', 'AXL'],
            ['4410410', 'Larracas', 'Lois Edd', 'Angeles', '', 'LOIS'],
            ['105302', 'Magno', 'Jacqueline', 'Bautista', '', 'JACKIE'],
            ['4413006', 'Mendoza', 'Jeimboy', 'Badiang', '', 'JIM'],
            ['209663', 'Mosquite', 'Ma. Victoria', 'Marpuri', '', 'VICKY'],
            ['102543', 'Pastor', 'Kristen', 'Granale', '', 'TEN'],
            ['101409', 'Reyes', 'Mark Anthony', 'Pagsuyoin', '', 'MARK'],
            ['4420797', 'Roncales', 'Lareine', '', '', 'REINE'],
            ['3407430', 'Rosales', 'Braian', 'Galet', '', 'BRAIAN'],
            ['4421880', 'Soriano', 'Judith', 'Bersamina', '', 'JUDITH'],
            ['4421662', 'Springael-De Castro', 'Nancy', 'Manalo', '', 'NANCY'],
            ['4414307', 'Villarete', 'John Carlo', 'Aspiras', '', 'JC']
        ];

        $claims = [
            // ['1100255', 'Buenafe', 'Maria Luisa', 'Natividad', '', 'MALU'],
            ['405469', 'Alumno', 'Maria Corazon', 'Jose', '', 'SONG'],
            ['102549', 'Anglo', 'Evelyn', 'Morillo', '', 'PATCHIE'],
            ['4414788', 'Chan', 'Raphael Benedict', 'Estanislao', '', 'ARBY'],
            ['103117', 'Leonidas', 'Sheila', 'Santos', '', 'LALA'],
            ['1902699', 'Mandreza', 'Nilgene', 'Cabalquinto', '', 'YHEN'],
            ['102533', 'Melendres', 'Jocelyn', 'Romero', '', 'LYN'],
            ['4420798', 'Palad', 'Ruben', 'Talampas', 'Jr', 'JONG'],
            ['102548', 'Sardea', 'Exequiel', 'Santos', 'Jr', 'DAWAN']
        ];

        $lnd = [
            // ['408433', 'Tatco', 'Analiza', 'Vega', '', 'ANA'],
            ['4421526', 'Salandanan', 'Jerryme', 'Enriquez', '', 'JERRYME'],
            ['107799', 'Celso', 'Jackielou', 'Aliño', '', 'JECK'],
            ['4421690', 'De Asis', 'Daisy', 'Alonzo', '', 'DAISY'],
            ['4420422', 'Dequiña', 'Jason', 'Bello', '', 'JASON'],
            ['4417174', 'Geronimo', 'Kimberly May', 'Natividad', '', 'KIM'],
            ['105522', 'Leonidas', 'Jayson', 'Isidro', '', 'JAYSON'],
            ['4421879', 'Macaldo', 'John Leslee', 'Manso', '', 'LESLEE'],
            ['4415247', 'Oprenario', 'Joemar', 'Castro', '', 'JOEMAR'],
            ['4420421', 'Tomas', 'Princess', 'Tadeo', '', 'CESS']
        ];

        $payroll = [
            // ['4417351', 'Flores', 'Robert Henry', 'Hipolito', '', 'HENRY'],
            ['4413893', 'Bepiñoso', 'Maureen', 'Marcelo', '', 'MAU'],
            ['4419382', 'Cruz', 'John Carlo', 'Castillon', '', 'CARLO'],
            ['4420864', 'San Buenaventura', 'Kaye Mari', 'Rodriguez', '', 'KAYE'],
            ['4419236', 'Turingan', 'Bryan', 'Hayuhay', '', 'BRYAN'],
            ['101226', 'Afurong', 'Richard', 'Bautista', '', 'RICHARD'],
            ['3409491', 'Andal', 'Devina Blessilda', 'Javier', '', 'DEVINA'],
            ['901676', 'Avellano', 'Georgina', 'Talagsad', '', 'GINA'],
            ['4410833', 'Eco', 'Algie', 'Punzalan', '', 'ALGIE'],
            ['2006136', 'Magboo', 'John Lazaro', 'Macario', '', 'JOHN LAZARO'],
            ['2101227', 'Magsalin', 'Ronald', 'Adriano', '', 'MAGS'],
            ['4413894', 'Padoga', 'Renardo', 'Oñipig', 'Jr', 'JR'],
            ['901670', 'Reyes', 'Lorna', 'Cruz', '', 'LORNA'],
            ['103116', 'Reyes', 'Rodalyn', 'Dela Cruz', '', 'DALYN'],
            ['102544', 'San Andres', 'Joseph', 'Magbitang', '', 'JOSEPH'],
            ['104854', 'Santos', 'Erwin', 'Alvento', '', 'ERWIN']
        ];

        $records = [
            // ['100970', 'Santos', 'Haydie', 'Ventura', '', 'HAYDIE'],
            ['4419381', 'Deduyo', 'Manny', 'Opelanio', '', 'MANNY'],
            ['4411246', 'Estayani', 'Robert', 'Samar', '', 'BERT'],
            ['4421561', 'Adonis', 'Roderick', 'Cabus', '', 'ROD'],
            ['102537', 'David', 'Catherine', 'Malonzo', '', 'CATHY'],
            ['102538', 'De Castro', 'Elaine', 'Diaz', '', 'ELAINE'],
            ['4411399', 'Ladica', 'Celestino', 'Polines', 'Jr', 'CELESTINO'],
            ['3409488', 'Lirio', 'Aileen', 'Cuartero', '', 'AILEEN'],
            ['103115', 'Portuguez', 'Michael', 'Sandrino', '', 'MICHAEL'],
            ['102541', 'Ramos', 'Arturo', 'Cruz', 'II', 'SONNY'],
            ['402224', 'Salandanan', 'Edilberto', 'Cruz', '', 'EBERT']
        ];

        $pm = [
            ['4420783', 'Cruz', 'Clifford', 'Antonio', '', 'CLIFFORD'],
            ['2300769', 'Vierne', 'Iluminada', 'Tiongson', '', 'GINA']
        ];

        $it = [
            ['4416266', 'Duza', 'Myls', 'Salazar', '', 'MYLS'],
            ['4422002', 'Guantia', 'Dale', 'Falcunit', '', 'DALE'],
            ['4416267', 'Valles', 'Darrel', 'Espejo', '', 'DARREL']
        ];

        $user_admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('12341234'),
            'department_id' => 1,
            'division_id' => 2,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);

        $dept_head = User::create([
            // 'emp_no' => '',
            'name' => 'Deaprtment Head',
            'username' => 'departmenthead',
            'email' => 'departmenthead@example.com',
            'password' => bcrypt('12341234'),
            'department_id' => 1,
            'division_id' => 1,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $admin_head = User::create([
            'emp_no' => '100561',
            'name' => 'Edwin B. Correa',
            'username' => 'coreaedwin',
            'password' => bcrypt('12341234'),
            'nickname' => 'EDWIN',
            'department_id' => 1,
            'division_id' => 3,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $payroll_head = User::create([
            'emp_no' => '4417351',
            'name' => 'Robert Henry H. Flores',
            'username' => 'floresroberthenry',
            'password' => bcrypt('12341234'),
            'nickname' => 'HENRY',
            'department_id' => 1,
            'division_id' => 4,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $records_head = User::create([
            'emp_no' => '100970',
            'name' => 'Haydie V. Santos',
            'username' => 'santoshaydie',
            'password' => bcrypt('12341234'),
            'nickname' => 'HAYDIE',
            'department_id' => 1,
            'division_id' => 5,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $claims_head = User::create([
            'emp_no' => '1100255',
            'name' => 'Maria Luisa N. Buenafe',
            'username' => 'buenafemarialuisa',
            'password' => bcrypt('12341234'),
            'nickname' => 'MALU',
            'department_id' => 1,
            'division_id' => 6,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $rsp_head = User::create([
            'emp_no' => '100325',
            'name' => 'Minerva V. Rosas',
            'username' => 'rosasminerva',
            'password' => bcrypt('12341234'),
            'nickname' => 'MINNIE',
            'department_id' => 1,
            'division_id' => 7,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $lnd_head = User::create([
            'emp_no' => '408433',
            'name' => 'Analiza V. Tatco',
            'username' => 'tatcoanaliza',
            'password' => bcrypt('12341234'),
            'nickname' => 'ANA',
            'department_id' => 1,
            'division_id' => 8,
            'is_active' => 1,
            'email_verified_at' => now()
        ]);
        $employee = User::create([
            'name' => 'Employee',
            'username' => 'employee',
            'email' => 'employee@example.com',
            'password' => bcrypt('12341234'),
            'is_active' => 1,
            'email_verified_at' => now()
        ]);

        $user_admin->assignRole('admin');
        $dept_head->assignRole('dept_head');
        $admin_head->assignRole('div_head');
        $payroll_head->assignRole('div_head');
        $records_head->assignRole('div_head');
        $claims_head->assignRole('div_head');
        $rsp_head->assignRole('div_head');
        $lnd_head->assignRole('div_head');
        $employee->assignRole('employee');

        foreach ($admin as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $admin_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 3,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $admin_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($rsp as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $rsp_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 7,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $rsp_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($claims as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $claims_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 6,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $claims_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($lnd as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $lnd_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 8,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $lnd_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($payroll as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $payroll_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 4,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $payroll_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($records as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $records_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 5,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $records_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($pm as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $pm_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 9,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $pm_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }

        foreach ($it as $userData) {
            $empNo = $userData[0];
            $lastName = $userData[1];
            $firstNameFull = $userData[2];
            $middleName = $userData[3];
            $suffix = $userData[4];
            $nickname = $userData[5];

            $middleInitial = '';
            if (!empty($middleName)) {
                $middleInitial = Str::upper(substr($middleName, 0, 1)) . '.';
            }

            $nameParts = [
                $firstNameFull,
                !empty($middleInitial) ? $middleInitial : null, // Include middle initial if available
                $lastName,
                !empty($suffix) ? $suffix : null, // Include suffix if available
            ];

            $fullName = implode(' ', array_filter($nameParts));

            $lastNameSlug = str_replace(' ', '', $lastName);
            $firstNameSlug = str_replace(' ', '', $firstNameFull); // Use the full first name here
            $username = Str::lower($lastNameSlug . $firstNameSlug);

            $it_loop = User::create([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => 1,
                'division_id' => 2,
                'is_active' => 1,
                'email_verified_at' => now(),
            ]);

            $it_loop->assignRole('hr_staff');

            $this->command->info("Created User ({$empNo}): {$fullName} | Username: {$username}");
        }
    }
}
