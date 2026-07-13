<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $department = Department::firstOrCreate([
            'department_name' => 'City Human Resource Development Office',
        ]);

        // Was missing entirely — this is why $divisionIds was undefined
        $divisionIds = $this->divisionIds($department->id);

        $admin = [
            // ['100561', 'Correa', 'Edwin', 'Bautista', '', 'EDWIN'],
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
            // ['2300769', 'Vierne', 'Iluminada', 'Tiongson', '', 'GINA']
        ];

        $it = [
            // ['4416266', 'Duza', 'Myls', 'Salazar', '', 'MYLS'],
            ['4422002', 'Guantia', 'Dale', 'Falcunit', '', 'DALE'],
            ['4416267', 'Valles', 'Darrel', 'Espejo', '', 'DARREL']
        ];

        $adminUser  = $this->seedUser([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('12341234'),
            'department_id' => $department->id,
            'division_id' => $divisionIds['Information Technology'] ?? null,
            'is_active' => 1,
            'email_verified_at' => now(),
        ], 'admin');

        $this->seedUser([
            'name' => 'Department Head',
            'username' => 'departmenthead',
            'email' => 'departmenthead@example.com',
            'password' => bcrypt('12341234'),
            'department_id' => $department->id,
            'division_id' => $divisionIds['Department Head'] ?? null,
            'is_active' => 1,
            'email_verified_at' => now(),
        ], 'dept_head');

        $divisionHeads = [
            ['100561', 'Edwin B. Correa', 'correaedwin', 'EDWIN', 'Administrative'],
            ['4417351', 'Robert Henry H. Flores', 'floresroberthenry', 'HENRY', 'Payroll'],
            ['100970', 'Haydie V. Santos', 'santoshaydie', 'HAYDIE', 'Records'],
            ['1100255', 'Maria Luisa N. Buenafe', 'buenafemarialuisa', 'MALU', 'Claims and Benefits'],
            ['100325', 'Minerva V. Rosas', 'rosasminerva', 'MINNIE', 'RSP'],
            ['408433', 'Analiza V. Tatco', 'tatcoanaliza', 'ANA', 'Learning and Development'],
            ['2300769', 'Iluminada T. Vierne', 'vierneiluminada', 'GINA', 'Performance Management'],
            ['4416266', 'Myls S. Duza', 'duzamyls', 'MYLS', 'Information Technology'],
        ];

        foreach ($divisionHeads as $head) {
            [$empNo, $name, $username, $nickname, $divisionName] = $head;

            $this->seedUser([
                'emp_no' => $empNo,
                'name' => $name,
                'username' => $username,
                'email' => $this->emailForUsername($username),
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => $department->id,
                'division_id' => $divisionIds[$divisionName] ?? null,
                'is_active' => 1,
                'email_verified_at' => now(),
            ], 'div_head');
        }

        $this->seedUser([
            'name' => 'Employee',
            'username' => 'employee',
            'email' => 'employee@example.com',
            'password' => bcrypt('12341234'),
            'is_active' => 1,
            'email_verified_at' => now(),
        ], 'employee');

        $this->seedGroup($admin, $department->id, $divisionIds['Administrative'] ?? null);
        $this->seedGroup($rsp, $department->id, $divisionIds['RSP'] ?? null);
        $this->seedGroup($claims, $department->id, $divisionIds['Claims and Benefits'] ?? null);
        $this->seedGroup($lnd, $department->id, $divisionIds['Learning and Development'] ?? null);
        $this->seedGroup($payroll, $department->id, $divisionIds['Payroll'] ?? null);
        $this->seedGroup($records, $department->id, $divisionIds['Records'] ?? null);
        $this->seedGroup($pm, $department->id, $divisionIds['Performance Management'] ?? null);
        $this->seedGroup($it, $department->id, $divisionIds['Information Technology'] ?? null);
    }

    private function seedGroup(array $users, int $departmentId, ?int $divisionId): void
    {
        foreach ($users as $userData) {
            [$empNo, $lastName, $firstNameFull, $middleName, $suffix, $nickname] = $userData;

            $fullName = $this->fullName($firstNameFull, $middleName, $lastName, $suffix);
            $username = $this->username($lastName, $firstNameFull);

            $user = $this->seedUser([
                'emp_no' => $empNo,
                'name' => $fullName,
                'username' => $username,
                'email' => $this->emailForUsername($username),
                'password' => bcrypt('12341234'),
                'nickname' => $nickname,
                'department_id' => $departmentId,
                'division_id' => $divisionId,
                'is_active' => 1,
                'email_verified_at' => now(),
            ], 'hr_staff');

            if (isset($this->command)) {
                $this->command->info("Seeded User ({$empNo}): {$fullName} | Username: {$user->username}");
            }
        }
    }

    private function seedUser(array $attributes, string $roleName): User
    {
        if (empty($attributes['username'])) {
            $attributes['username'] = $this->usernameFromName($attributes['name']);
        }

        if (empty($attributes['email'])) {
            $attributes['email'] = $this->emailForUsername($attributes['username']);
        }

        $lookup = ['username' => $attributes['username']];
        $user = User::updateOrCreate($lookup, $attributes);

        if (! empty($roleName)) {
            $user->assignRole($roleName);
        }

        return $user;
    }

    private function divisionIds(int $departmentId): array
    {
        $names = [
            'Department Head',
            'Information Technology',
            'Administrative',
            'Payroll',
            'Records',
            'Claims and Benefits',
            'RSP',
            'Learning and Development',
            'Performance Management',
        ];

        $ids = [];

        foreach ($names as $name) {
            $division = Division::firstOrCreate(
                [
                    'division_name' => $name,
                    'department_id' => $departmentId,
                ],
                [
                    'division_name' => $name,
                    'department_id' => $departmentId,
                ]
            );

            $ids[$name] = $division->id;
        }

        return $ids;
    }

    private function fullName(string $firstNameFull, ?string $middleName, string $lastName, ?string $suffix): string
    {
        $middleInitial = '';

        if (! empty($middleName)) {
            $middleInitial = Str::upper(Str::substr($middleName, 0, 1)) . '.';
        }

        return implode(' ', array_filter([
            $firstNameFull,
            $middleInitial ?: null,
            $lastName,
            $suffix ?: null,
        ]));
    }

    private function username(string $lastName, string $firstNameFull): string
    {
        $username = Str::ascii($lastName . $firstNameFull);
        $username = preg_replace('/[^A-Za-z0-9]/', '', $username);

        return Str::lower($username);
    }

    private function usernameFromName(string $name): string
    {
        $username = Str::ascii($name);
        $username = preg_replace('/[^A-Za-z0-9]/', '', $username);

        return Str::lower($username);
    }

    private function emailForUsername(string $username): string
    {
        $username = Str::lower(Str::ascii($username));
        $username = preg_replace('/[^a-z0-9._-]/', '', $username);

        return $username . '@employee-care.local';
    }
}
