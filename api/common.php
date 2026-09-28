<?php
/**
 * Shared helpers for every endpoint. Endpoints include this file instead of db.php:
 *
 *     require_once "common.php";
 *
 * It loads db.php (the database connection, one per server, not in git) and adds:
 *   - sessions: who is signed in, stored server-side (Sessions table)
 *   - requireLogin() / requireAdmin() guards, including a CSRF check on every POST
 *   - sendJson() / fail() responses in the same {"error": "..."} format as before
 *   - validateFields() input validation
 *   - logActivity() for the activity feed and the admin audit log
 */

require_once __DIR__ . "/db.php"; // provides $pdo and getRequestData()

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

const SESSION_LIFETIME = 8 * 60 * 60;   // signed out after 8 hours of inactivity
const MAX_FAILED_LOGINS = 5;            // wrong passwords in a row before a lockout
const LOCKOUT_MINUTES = 15;
const MIN_PASSWORD_LENGTH = 8;
const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
const MAX_CSV_BYTES = 1024 * 1024;
const MAX_CSV_ROWS = 1000;

// The pages and the API live on the same site and the browser sends the session cookie
// by itself, so the cross-origin (CORS) headers from db.php are not needed any more.
header_remove("Access-Control-Allow-Origin");
header_remove("Access-Control-Allow-Methods");
header_remove("Access-Control-Allow-Headers");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

date_default_timezone_set("UTC");
$pdo->exec("SET time_zone = '+00:00'"); // store and compare all DATETIMEs in UTC

// Any error nobody caught becomes a JSON 500 (details go to Apache's error.log, not the browser).
set_exception_handler(function (Throwable $e) {
    error_log("[contacts-api] " . get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["error" => "Something went wrong on the server"]);
});

// ---------------------------------------------------------------------------
// Responses and input
// ---------------------------------------------------------------------------

function sendJson($data, int $status = 200): never
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/** Stop with an error, e.g. fail(404, "Contact not found"). $details = one message per form field. */
function fail(int $status, string $message, array $details = [], ?string $code = null): never
{
    $body = ["error" => $message];
    if ($code !== null) {
        $body["code"] = $code;
    }
    if ($details) {
        $body["details"] = $details;
    }
    sendJson($body, $status);
}

/** Only accept the expected HTTP method. POST bodies must be JSON (or multipart for uploads). */
function requireMethod(string $method): void
{
    if ($_SERVER["REQUEST_METHOD"] !== $method) {
        fail(405, "Use $method for this endpoint");
    }
    if ($method === "POST" && (int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 0) {
        $type = strtolower($_SERVER["CONTENT_TYPE"] ?? "");
        // A cross-site HTML form can't send JSON, which is one of our CSRF defences.
        if (strpos($type, "application/json") === false && strpos($type, "multipart/form-data") === false) {
            fail(415, "Send the request body as JSON");
        }
    }
}

/** The request's parameters: the JSON body for POST, form fields for uploads, the query string for GET. */
function getInput(): array
{
    static $input = null;
    if ($input !== null) {
        return $input;
    }
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $input = $_GET;
    } elseif (strpos(strtolower($_SERVER["CONTENT_TYPE"] ?? ""), "multipart/form-data") !== false) {
        $input = $_POST;
    } else {
        $data = getRequestData();
        if ($data === null && trim((string) file_get_contents("php://input")) !== "") {
            fail(400, "Request body must be valid JSON");
        }
        $input = is_array($data) ? $data : [];
    }
    return $input;
}

/** A required positive integer id from the input, e.g. requireId("id") or requireId("targetUserId"). */
function requireId(string $key = "id"): int
{
    $value = getInput()[$key] ?? null;
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        if ((int) $value > 0) {
            return (int) $value;
        }
    }
    fail(400, "Missing or invalid \"$key\"");
}

/** A text parameter, trimmed. Anything that isn't plain text (e.g. ?term[]=x) counts as empty. */
function inputText(string $key): string
{
    $value = getInput()[$key] ?? "";
    return is_string($value) ? trim($value) : "";
}

/** Whole-number query parameter clamped to a range (for page numbers etc.). */
function inputInt(string $key, int $default, int $min, int $max): int
{
    $value = getInput()[$key] ?? null;
    $value = is_numeric($value) ? (int) $value : $default;
    return max($min, min($max, $value));
}

/** "%" and "_" typed by the user should be searched for literally, not act as LIKE wildcards. */
function likePattern(string $term): string
{
    return "%" . addcslashes($term, "%_\\") . "%";
}

// ---------------------------------------------------------------------------
// UTF-8 text helpers (work with or without PHP's mbstring extension)
// ---------------------------------------------------------------------------

function textLength(string $s): int
{
    return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : (int) preg_match_all("/./su", $s);
}

function textLimit(string $s, int $max): string
{
    if (function_exists("mb_substr")) {
        return mb_substr($s, 0, $max, "UTF-8");
    }
    preg_match("/^.{0,$max}/su", $s, $m);
    return $m[0] ?? "";
}

/** Excel on Windows saves CSV as Windows-1252; convert anything that isn't valid UTF-8. */
function toUtf8(string $s): string
{
    if (preg_match("//u", $s)) {
        return $s;
    }
    if (function_exists("iconv")) {
        $converted = @iconv("Windows-1252", "UTF-8//TRANSLIT", $s);
        if ($converted !== false) {
            return $converted;
        }
    }
    if (function_exists("mb_convert_encoding")) {
        return mb_convert_encoding($s, "UTF-8", "Windows-1252");
    }
    return (string) preg_replace_callback("/[\x80-\xFF]/", function ($m) {
        return chr(0xC0 | (ord($m[0]) >> 6)) . chr(0x80 | (ord($m[0]) & 0x3F));
    }, $s);
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

/**
 * Check input fields against simple rules and return the cleaned values.
 *
 *   $clean = validateFields($data, [
 *       "firstName" => ["required", "max:50"],
 *       "email"     => ["email", "max:254"],
 *   ]);
 *
 * Fields without "required" may be empty (they come back as null). On any error this
 * stops with a 422 listing one message per field, which the pages show under each input.
 */
function validateFields(array $input, array $rules): array
{
    [$clean, $errors] = checkFields($input, $rules);
    if ($errors) {
        fail(422, "Please fix the highlighted fields", $errors, "validation_failed");
    }
    return $clean;
}

/** Same checks as validateFields(), but returns [$clean, $errors] instead of stopping. */
function checkFields(array $input, array $rules): array
{
    $clean = [];
    $errors = [];

    foreach ($rules as $field => $fieldRules) {
        $value = $input[$field] ?? null;
        if (is_string($value) && !in_array("password", $fieldRules, true)) { // passwords are kept exactly as typed
            $value = trim($value);
        }
        $label = ucfirst(strtolower(preg_replace("/(?<!^)[A-Z]/", " $0", $field)));

        if ($value === null || $value === "" || $value === []) {
            if (in_array("required", $fieldRules, true)) {
                $errors[$field] = "$label is required";
            } else {
                $clean[$field] = in_array("list", $fieldRules, true) ? [] : null;
            }
            continue;
        }

        $error = null;
        foreach ($fieldRules as $rule) {
            $parts = explode(":", $rule, 2);
            $name = $parts[0];
            $arg = $parts[1] ?? null;
            $isText = is_string($value);

            if ($name === "max" && (!$isText || textLength($value) > (int) $arg)) {
                $error = $isText ? "$label must be at most $arg characters" : "$label must be text";
            } elseif ($name === "min" && (!$isText || textLength($value) < (int) $arg)) {
                $error = "$label must be at least $arg characters";
            } elseif ($name === "email" && !($isText && filter_var($value, FILTER_VALIDATE_EMAIL))) {
                $error = "$label must be a valid email address";
            } elseif ($name === "phone" && !($isText && preg_match("/^[0-9+().\\-\\s x]{7,30}$/i", $value))) {
                $error = "$label must be a valid phone number";
            } elseif ($name === "date" && !($isText && isValidDate($value))) {
                $error = "$label must be a date (YYYY-MM-DD)";
            } elseif ($name === "past" && $isText && $value > date("Y-m-d")) {
                $error = "$label cannot be in the future";
            } elseif ($name === "username" && !($isText && preg_match("/^[A-Za-z0-9_.\\-]{3,50}$/", $value))) {
                $error = "$label must be 3-50 letters, numbers, dots, dashes or underscores";
            } elseif ($name === "color" && !($isText && preg_match("/^#[0-9a-fA-F]{6}$/", $value))) {
                $error = "$label must be a color like #ec4899";
            } elseif ($name === "in" && !in_array($value, explode(",", (string) $arg), true)) {
                $error = "$label must be one of: " . str_replace(",", ", ", (string) $arg);
            } elseif ($name === "bool" && !is_bool($value)) {
                $error = "$label must be true or false";
            } elseif ($name === "password" && $isText && strlen($value) > 72) {
                $error = "$label must be at most 72 characters";
            } elseif ($name === "list" && !(is_array($value) && array_values($value) === $value
                && count(array_filter($value, function ($v) { return !is_int($v) || $v <= 0; })) === 0)) {
                $error = "$label must be a list of ids";
            }
            if ($error !== null) {
                break;
            }
        }

        if ($error !== null) {
            $errors[$field] = $error;
        } else {
            $clean[$field] = $value;
        }
    }

    return [$clean, $errors];
}

function isValidDate(string $value): bool
{
    $d = DateTime::createFromFormat("!Y-m-d", $value);
    return $d !== false && $d->format("Y-m-d") === $value && $value >= "1900-01-01";
}

function passwordRules(): array
{
    return ["required", "min:" . MIN_PASSWORD_LENGTH, "password"];
}

// ---------------------------------------------------------------------------
// Sessions: stored in the Sessions table instead of PHP's temp files, because Ubuntu's
// cleanup job deletes those after 24 minutes no matter what timeout we choose.
// PHP calls these methods itself; only signed-in sessions are saved.
// ---------------------------------------------------------------------------

class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id)
    {
        $stmt = $this->pdo->prepare("SELECT Data FROM Sessions WHERE ID = ? AND LastActivity > ?");
        $stmt->execute([$id, time() - SESSION_LIFETIME]);
        $data = $stmt->fetchColumn();
        return is_string($data) ? $data : "";
    }

    public function write($id, $data): bool
    {
        $userId = isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
        if ($userId === null) {
            return $this->destroy($id); // visitors who aren't signed in don't create rows
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO Sessions (ID, UserID, Data, LastActivity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE UserID = VALUES(UserID), Data = VALUES(Data), LastActivity = VALUES(LastActivity)"
        );
        $stmt->execute([$id, $userId, $data, time()]);
        return true;
    }

    public function destroy($id): bool
    {
        $this->pdo->prepare("DELETE FROM Sessions WHERE ID = ?")->execute([$id]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxLifetime)
    {
        $stmt = $this->pdo->prepare("DELETE FROM Sessions WHERE LastActivity < ?");
        $stmt->execute([time() - SESSION_LIFETIME]);
        return $stmt->rowCount();
    }

    /** Only ids we issued are accepted (prevents session fixation). */
    public function validateId($id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM Sessions WHERE ID = ? AND LastActivity > ?");
        $stmt->execute([$id, time() - SESSION_LIFETIME]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTimestamp($id, $data): bool
    {
        if (isset($_SESSION["uid"])) {
            $this->pdo->prepare("UPDATE Sessions SET LastActivity = ? WHERE ID = ?")->execute([time(), $id]);
        }
        return true;
    }
}

function startSession(PDO $pdo): void
{
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");

    ini_set("session.use_strict_mode", "1");
    ini_set("session.gc_maxlifetime", (string) SESSION_LIFETIME);
    ini_set("session.gc_probability", "1");
    ini_set("session.gc_divisor", "100");
    session_set_save_handler(new DatabaseSessionHandler($pdo), true);
    session_name("CMSESSID");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $https,
        "httponly" => true,    // JavaScript can't read the cookie
        "samesite" => "Lax",
    ]);
    session_start();
}

startSession($pdo);

// ---------------------------------------------------------------------------
// Signed-in user
// ---------------------------------------------------------------------------

/**
 * The signed-in user's Users row, re-read from the database on every request so that
 * disabling, demoting or "sign out everywhere" takes effect immediately. Null if nobody.
 */
function currentUser(): ?array
{
    global $pdo;
    static $loaded = false, $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = (int) ($_SESSION["uid"] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE ID = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row || (int) $row["IsDisabled"] === 1 || (int) $row["SessionVersion"] !== (int) ($_SESSION["ver"] ?? -1)) {
        signOut();
        return null;
    }
    $user = $row;
    return $user;
}

/**
 * Stop with 401 unless someone is signed in. POST requests must also carry the CSRF token
 * (header X-CSRF-Token) that Login.php / Me.php handed out.
 */
function requireLogin(bool $allowWhilePasswordChangeRequired = false): array
{
    $user = currentUser();
    if ($user === null) {
        fail(401, "Please sign in", [], "unauthorized");
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
        if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
            fail(403, "Your session token is missing or expired. Please sign in again.", [], "csrf_failed");
        }
    }
    if ((int) $user["MustChangePassword"] === 1 && !$allowWhilePasswordChangeRequired) {
        fail(403, "You must choose a new password before continuing", [], "password_change_required");
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();
    if ($user["Role"] !== "Admin") {
        fail(403, "Admin access required", [], "forbidden");
    }
    return $user;
}

function signIn(array $userRow): void
{
    session_regenerate_id(true); // a fresh session id at sign-in prevents session fixation
    $_SESSION["uid"] = (int) $userRow["ID"];
    $_SESSION["ver"] = (int) $userRow["SessionVersion"];
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

function signOut(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** The user fields the pages need. Never includes the password hash. */
function userJson(array $row): array
{
    return [
        "id" => (int) $row["ID"],
        "firstName" => $row["FirstName"],
        "lastName" => $row["LastName"],
        "login" => $row["Login"],
        "role" => $row["Role"],
        "mustChangePassword" => (bool) $row["MustChangePassword"],
        "dateCreated" => $row["DateCreated"],
        "lastLoginAt" => $row["LastLoginAt"],
        "csrfToken" => $_SESSION["csrf"] ?? "",
        "error" => "",
    ];
}

// ---------------------------------------------------------------------------
// Activity log (dashboard feed + admin audit log)
// ---------------------------------------------------------------------------

/**
 * Record something that happened. $summary is written without the actor's name
 * ("added contact Ben Brown"); pages show it as "<name> <summary>".
 * $targetUserId is the person it affects (e.g. who a contact was shared with).
 * $actorUserId defaults to the signed-in user; pass null when nobody is (failed sign-ins).
 */
function logActivity(string $action, string $summary, ?string $entityType = null, ?int $entityId = null, ?int $targetUserId = null, ?int $actorUserId = -1): void
{
    global $pdo;
    if ($actorUserId === -1) {
        $actorUserId = isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO ActivityLog (ActorUserID, TargetUserID, ActorName, TargetName, Action, EntityType, EntityID, Summary, IPAddress)
             VALUES (?, ?, (SELECT CONCAT(FirstName, ' ', LastName) FROM Users WHERE ID = ?),
                     (SELECT CONCAT(FirstName, ' ', LastName) FROM Users WHERE ID = ?), ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $actorUserId, $targetUserId, $actorUserId, $targetUserId,
            $action, $entityType, $entityId, textLimit($summary, 255), $_SERVER["REMOTE_ADDR"] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log("[contacts-api] activity log write failed: " . $e->getMessage()); // never break the real action
    }
}

/** SELECT used by the activity feed and the audit log (joins in current names). */
const ACTIVITY_SELECT = "
    SELECT a.ID, a.Action, a.Summary, a.EntityType, a.EntityID, a.DateCreated, a.IPAddress,
           a.ActorUserID, a.TargetUserID,
           COALESCE(CONCAT(au.FirstName, ' ', au.LastName), CONCAT(a.ActorName, ' (deleted user)')) AS ActorName,
           COALESCE(CONCAT(tu.FirstName, ' ', tu.LastName), CONCAT(a.TargetName, ' (deleted user)')) AS TargetName,
           au.Login AS ActorLogin
    FROM ActivityLog a
    LEFT JOIN Users au ON au.ID = a.ActorUserID
    LEFT JOIN Users tu ON tu.ID = a.TargetUserID";

/** Shape activity rows for the pages. IP addresses are only included for admins. */
function activityJson(array $rows, bool $includeIp = false): array
{
    $me = (int) ($_SESSION["uid"] ?? 0);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            "id" => (int) $r["ID"],
            "action" => $r["Action"],
            "summary" => $r["Summary"],
            "actorName" => $r["ActorName"] ?? ($r["TargetName"] ?? "Someone"),
            "actorLogin" => $r["ActorLogin"],
            "isYou" => $r["ActorUserID"] !== null && (int) $r["ActorUserID"] === $me,
            "targetName" => $r["TargetName"],
            "entityType" => $r["EntityType"],
            "entityId" => $r["EntityID"] !== null ? (int) $r["EntityID"] : null,
            "ipAddress" => $includeIp ? $r["IPAddress"] : null,
            "dateCreated" => $r["DateCreated"],
        ];
    }
    return $out;
}

/** Admins can't be removed if they're the last one able to sign in. */
function isLastActiveAdmin(array $userRow): bool
{
    global $pdo;
    if ($userRow["Role"] !== "Admin" || (int) $userRow["IsDisabled"] === 1) {
        return false;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM Users WHERE Role = 'Admin' AND IsDisabled = 0")->fetchColumn() <= 1;
}

/** Load a user by id or stop with 404. */
function findUserOr404(int $id): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE ID = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail(404, "User not found");
    }
    return $row;
}
