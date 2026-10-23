<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? 'health';
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data);
  exit;
}

if ($action === 'health' && $method === 'GET') {
  respond(['ok' => true, 'service' => 'SkyRoute PHP API']);
}

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : require __DIR__ . '/config.example.php';
$databasePort = (int)($config['port'] ?? 3306);

$socket = @fsockopen($config['host'], $databasePort, $socketError, $socketMessage, 2);
if ($socket === false) {
  respond(['error' => "Cannot reach MySQL at {$config['host']}:{$databasePort}. Start MySQL and check its configured port."], 500);
}
stream_set_timeout($socket, 2);
$greeting = fread($socket, 1);
$socketStatus = stream_get_meta_data($socket);
fclose($socket);
if ($greeting === false || $greeting === '' || $socketStatus['timed_out']) {
  respond(['error' => "MySQL at {$config['host']}:{$databasePort} accepted the connection but did not send its greeting. Check the XAMPP MySQL error log; the server may not have completed startup."], 500);
}

try {
  $pdo = new PDO(
    "mysql:host={$config['host']};port={$databasePort};dbname={$config['database']};charset={$config['charset']}",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 3]
  );
} catch (Throwable $e) {
  respond(['error' => 'Could not connect to the SkyRoute database. Check backend/config.php, then confirm that the skyroute database exists in phpMyAdmin.'], 500);
}

if ($action === 'database-health' && $method === 'GET') {
  respond(['ok' => true, 'database' => 'connected']);
}

function body(): array {
  $data = json_decode(file_get_contents('php://input'), true);
  return is_array($data) ? $data : [];
}

function stringValue(array $data, string $key): string {
  return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : '';
}

function flightRow(array $row): array {
  $row['price'] = (float)$row['price'];
  $row['seats_available'] = (int)$row['seats_available'];
  $row['duration_minutes'] = (int)$row['duration_minutes'];
  return $row;
}

function authorizationHeader(): string {
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  foreach ($headers as $name => $value) {
    if (strtolower($name) === 'authorization') return $value;
  }
  return $_SERVER['HTTP_AUTHORIZATION'] ?? '';
}

function authenticatedUser(PDO $pdo): ?array {
  $header = authorizationHeader();
  if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) return null;
  $stmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, t.token_hash
     FROM api_tokens t JOIN users u ON u.id = t.user_id
     WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP()'
  );
  $stmt->execute([hash('sha256', strtolower($matches[1]))]);
  return $stmt->fetch() ?: null;
}

function issueToken(PDO $pdo, int $userId): string {
  $token = bin2hex(random_bytes(32));
  $stmt = $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
  $stmt->execute([$userId, hash('sha256', $token)]);
  return $token;
}

if ($action === 'register' && $method === 'POST') {
  $data = body();
  $name = stringValue($data, 'name');
  $email = strtolower(stringValue($data, 'email'));
  $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
  if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    respond(['error' => 'Enter a name, a valid email, and a password with at least 8 characters.'], 422);
  }

  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $token = issueToken($pdo, $userId);
    $pdo->commit();
    respond(['token' => $token, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email]], 201);
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') respond(['error' => 'An account with this email already exists. Please sign in.'], 409);
    respond(['error' => 'Could not create your account. Please try again.'], 500);
  }
}

if ($action === 'login' && $method === 'POST') {
  $data = body();
  $email = strtolower(stringValue($data, 'email'));
  $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
  $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(['error' => 'Email or password is incorrect.'], 401);
  }
  $token = issueToken($pdo, (int)$user['id']);
  respond([
    'token' => $token,
    'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']],
  ]);
}

if ($action === 'me' && $method === 'GET') {
  $user = authenticatedUser($pdo);
  if (!$user) respond(['error' => 'Please sign in to continue.'], 401);
  respond(['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']]);
}

if ($action === 'logout' && $method === 'POST') {
  $user = authenticatedUser($pdo);
  if ($user) {
    $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
    $stmt->execute([$user['token_hash']]);
  }
  respond(['message' => 'Signed out']);
}

if ($action === 'flights' && $method === 'GET') {
  $origin = trim($_GET['origin'] ?? '');
  $destination = trim($_GET['destination'] ?? '');
  $date = trim($_GET['date'] ?? '');
  $sql = 'SELECT * FROM flights WHERE seats_available > 0';
  $params = [];
  if ($origin !== '') { $sql .= ' AND (origin LIKE ? OR origin_code LIKE ?)'; $params[] = "%$origin%"; $params[] = "%$origin%"; }
  if ($destination !== '') { $sql .= ' AND (destination LIKE ? OR destination_code LIKE ?)'; $params[] = "%$destination%"; $params[] = "%$destination%"; }
  if ($date !== '') { $sql .= ' AND DATE(departure_time) = ?'; $params[] = $date; }
  $sql .= ' ORDER BY departure_time ASC LIMIT 100';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  respond(array_map('flightRow', $stmt->fetchAll()));
}

if ($action === 'flight' && $method === 'GET') {
  $term = trim($_GET['q'] ?? '');
  if ($term === '') respond(['error' => 'Enter a flight number or route code.'], 400);
  $stmt = $pdo->prepare('SELECT * FROM flights WHERE flight_number LIKE ? OR origin_code LIKE ? OR destination_code LIKE ? ORDER BY departure_time LIMIT 30');
  $stmt->execute(["%$term%", "%$term%", "%$term%"]);
  respond(array_map('flightRow', $stmt->fetchAll()));
}

if ($action === 'book' && $method === 'POST') {
  $user = authenticatedUser($pdo);
  if (!$user) respond(['error' => 'Please sign in before booking a flight.'], 401);
  $data = body();
  $flightId = filter_var($data['flight_id'] ?? null, FILTER_VALIDATE_INT);
  $name = stringValue($data, 'name');
  $email = strtolower(stringValue($data, 'email'));
  $phone = stringValue($data, 'phone');
  $seats = filter_var($data['seats'] ?? null, FILTER_VALIDATE_INT);
  if (!$flightId || mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || !$seats || $seats < 1 || $seats > 8) {
    respond(['error' => 'Enter valid passenger details and choose 1–8 seats.'], 422);
  }
  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM flights WHERE id = ? FOR UPDATE');
    $stmt->execute([$flightId]);
    $flight = $stmt->fetch();
    if (!$flight || (int)$flight['seats_available'] < $seats) {
      $pdo->rollBack();
      respond(['error' => 'This flight does not have enough seats available.'], 409);
    }
    $ref = 'SR' . strtoupper(bin2hex(random_bytes(3)));
    $total = (float)$flight['price'] * $seats;
    $stmt = $pdo->prepare('INSERT INTO bookings (user_id, booking_ref, flight_id, passenger_name, passenger_email, passenger_phone, seats, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int)$user['id'], $ref, $flightId, $name, $email, $phone, $seats, $total]);
    $pdo->prepare('UPDATE flights SET seats_available = seats_available - ? WHERE id = ?')->execute([$seats, $flightId]);
    $pdo->commit();
    respond(['message' => 'Booking confirmed', 'booking_ref' => $ref, 'total_amount' => $total, 'flight_number' => $flight['flight_number']], 201);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['error' => 'Could not complete booking. Please try again.'], 500);
  }
}

if ($action === 'booking' && $method === 'GET') {
  $user = authenticatedUser($pdo);
  if (!$user) respond(['error' => 'Please sign in to view your bookings.'], 401);
  $ref = strtoupper(trim($_GET['ref'] ?? ''));
  if ($ref === '') respond(['error' => 'Enter your booking reference.'], 400);
  $stmt = $pdo->prepare(
    'SELECT b.booking_ref, b.passenger_name, b.passenger_email, b.seats, b.total_amount, b.booking_status, b.created_at,
      f.flight_number, f.airline, f.origin, f.origin_code, f.destination, f.destination_code, f.departure_time,
      f.arrival_time, f.status AS flight_status
     FROM bookings b JOIN flights f ON f.id = b.flight_id
     WHERE b.booking_ref = ? AND b.user_id = ?'
  );
  $stmt->execute([$ref, (int)$user['id']]);
  $booking = $stmt->fetch();
  if (!$booking) respond(['error' => 'No booking found for that reference in your account.'], 404);
  $booking['seats'] = (int)$booking['seats'];
  $booking['total_amount'] = (float)$booking['total_amount'];
  respond($booking);
}

respond(['error' => 'Unknown action.'], 404);
