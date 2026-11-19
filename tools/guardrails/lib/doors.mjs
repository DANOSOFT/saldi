// tools/guardrails/lib/doors.mjs - the door map: which paths and code markers make a change one-way,
// which need care, and which station, reviewer and proof each one asks for.
//
// This file is the one home for the list. `check.mjs --station` routes a change
// by it (lib/station.mjs), and the pr-body check holds a PR's "Door:" against it.
//
// Doors. one-way: once merged and used, the change can leave something a revert
// does not take back (ledger rows, an issued number, a schema or data change,
// money moved, a mail or shop update sent, deleted data, data shown to the wrong
// tenant). two-way with care: a revert undoes it, but while it is live it can hit
// every tenant or feed a one-way path later; the PR still says "two-way".
//
// The map is a floor, never a verdict: a change that writes the books from an
// unlisted file is still one-way (#917 and #966 reached the books that way). A
// change with no match is "no detected one-way match", not "two-way".
//
// A row:
//   id, door ("one-way" | "care"), station, party (one-way rows: 1a-1d), reviewer
//   (first name), tier (the taster tier the row adds; null adds none), why (what a
//   revert does not undo), examples (past PRs);
//   paths    globs: "*" and "?" as in fnmatch, so "*" also crosses "/"; "{a,b}"
//            expands; a pattern ending in "/" is the whole folder;
//   except   globs the row leaves to another row;
//   markers  {marker, pattern, in?} matched on added code lines the lexer does not establish as comments (a marker
//            may have several patterns and hits a line once; `in` limits a pattern to the files
//            those globs match),
//            outside tests/, e2e/, doc/, tools/ and vendored code (MARKER_FILES);
//   markersIn  where the markers count: a glob list, or "row-paths" for the row's
//            own paths; markersEverywhere: any text file, comments included;
//   markerTier the tier when the row's own markers hit;
//   crossCheck false keeps the row out of the pr-body door cross-check;
//   proof    {text, e2e}: the minimum proof in the PR, and the end-to-end run it
//            asks for, strictest first: pr (a run on the PR), pr-or-flow (that, or
//            a run of the business flow the change touches), flow (a run of that
//            flow in test mode), where-available (a run on the PR where one covers
//            the change), none. A care row without one adds its station's proof.
//
// Edits to this map are approved by Nicolai. The reviewer column is a proposal
// ("forslag") until Nicolai adopts it.
//
// History:
// 20261103 CL/LH Created: the door map, its stations and proofs, and the TRUNCATE and password markers.
// 20261104 CL/LH The password marker is default-deny: a value counts unless the reader shows all of it is a reference.
// 20261106 CL/LH The plain subset; one definition of a line terminator; PHP 8.2's tokenizer reads PHP (lib/php.mjs).
// 20261107 CL/LH A changed line is read to the end of what it opens, the unchanged lines below it included.
// 20261107 CL/LH A piece of PHP outside HTML's data state, inside a name or beside one leaves HTML's state unknown.

/** The two approvers, in the order they lead: where the matches name both, the first leads. */
export const APPROVERS = ["Nicolai", "Saul"];

/**
 * Who may approve a door exception in a PR description, by GitHub login and the initials they sign
 * with. A `guardrails-allow pr-body` line counts only when the description's edit history shows one
 * of these logins added it, signed with that person's initials, on a PR they did not author
 * (checks/pr-body.mjs). GITHUB_TOKEN cannot read team membership, so the list lives here.
 */
export const REVIEWERS = [
  { name: "Nicolai", initials: "NTR", login: "SirRolin" },
  { name: "Saul", initials: "SZ", login: "ZaynSaul" },
];

/** "forslag" until Nicolai adopts the reviewer column, then "adopted". */
export const REVIEWER_LABEL = "forslag";

/** Inside Envejsdørene the party follows the blast-radius scale: customers and money, the books, every tenant, access. */
export const PARTY_ORDER = ["1b", "1a", "1c", "1d"];

/** For each tier field, the strictest value first. */
export const STRICTNESS = {
  level: ["deep", "standard", "quick"],
  effort: ["xhigh", "high"],
  longContext: ["always", "if-large-change", "never"],
  recheck: ["xhigh", "low"],
  e2e: ["pr", "pr-or-flow", "flow", "where-available", "none"],
};

/** Taster tiers, without model names. The strictest value of each field over every matched source wins. */
export const TIERS = {
  books: { level: "deep", effort: "xhigh", longContext: "always", securityPass: false, recheck: "xhigh" },
  payments: { level: "deep", effort: "xhigh", longContext: "always", securityPass: true, recheck: "xhigh" },
  outbound: { level: "deep", effort: "xhigh", longContext: "never", securityPass: true, recheck: "xhigh" },
  structure: { level: "standard", effort: "high", longContext: "never", securityPass: false, recheck: "xhigh" },
  dataStep: { level: "deep", effort: "xhigh", longContext: "always", securityPass: false, recheck: "xhigh" },
  access: { level: "deep", effort: "xhigh", longContext: "always", securityPass: true, recheck: "xhigh" },
  care: { level: "standard", effort: "high", longContext: "if-large-change", securityPass: false, recheck: "low" },
  gate: { level: "standard", effort: "high", longContext: "never", securityPass: false, recheck: "low" },
  screenLight: { level: "quick", effort: "high", longContext: "if-large-change", securityPass: false, recheck: "low" },
  screen: { level: "standard", effort: "high", longContext: "if-large-change", securityPass: false, recheck: "low" },
};

/** The stations a path can reach, in rule order: the first rule that matches owns the plate. */
export const STATIONS = {
  envejsdorene: {
    name: "Envejsdørene",
    rule: 1,
    parties: {
      "1b": {
        name: "Betaling og afsendelse",
        proof:
          "Test mode only, with the request or mail shown. Two deliveries of the same callback at the same moment give one effect, " +
          "and a run stopped after the provider acted but before Saldi recorded it converges on the next run. " +
          "Each case asserts the provider's state and the stored payment state.",
      },
      "1a": {
        name: "Bogføringen",
        proof:
          "A characterization test on the real database engine that asserts the rows written, uses the five values " +
          "(1250.500, 0.005, a credit line, a foreign currency and zero) and fails on base.",
      },
      "1c": {
        name: "Opdateringen",
        proof:
          "The rehearsal on Postgres and on MySQL or MariaDB, because CI has no MySQL: the step runs twice and the second run " +
          "changes no rows, an interrupted run converges at the next login, two logins at once leave it intact, and a login " +
          "works afterwards. A fresh tenant ends with the same structure as an upgraded one when the change reaches both.",
      },
      "1d": {
        name: "Adgang og sletning",
        proof:
          "auth: a denied case, an empty grant list and an allowed case, each asserting the expected result. " +
          "destructive and deploy: what is deleted or run, shown on a disposable copy. " +
          "secrets: nothing secret in the repo, and a rotation first if a secret was pushed.",
      },
    },
  },
  faellesgryden: {
    name: "Fællesgryden",
    rule: 2,
    tier: TIERS.care,
    proof: {
      text: "Before and after on every entry point the change reaches, plus the related PHPUnit suite green and not skipped.",
      e2e: "where-available",
    },
  },
  gartneren: {
    name: "Gartneren",
    rule: 3,
    reviewer: "Nicolai",
    tier: TIERS.gate,
    proof: { text: "The check fails on its proof commit, passes on base, and runs the same way locally and in CI.", e2e: "none" },
    /** Environment files add the image proof to Gartneren's. */
    image: { paths: ["Dockerfile", "docker-entrypoint.sh"], text: "For Dockerfile and docker-entrypoint.sh: the image builds, and Guardrails and Tests pass inside it." },
  },
  "skaerm-og-tekst": {
    name: "Skærm og tekst",
    rule: 4,
    reviewer: "Saul",
    tier: TIERS.screen,
    /** Every changed path is docs, tests, texts or CSS: the quick tier. */
    light: { paths: ["doc/", "*.md", "tests/", "e2e/", "*.css"], rows: ["texts"], tier: TIERS.screenLight },
    proof: { text: "Before and after in the browser on a local tenant, with PHP warnings visible.", e2e: "none" },
  },
};

/**
 * Gate paths: the repository's own tools and rules (Gartneren). The deploy workflows
 * are the `deploy` row instead.
 */
export const GATE = {
  paths: [".github/", "tools/guardrails/", "tools/ci/", "AGENTS.md", "CLAUDE.md", "doc/ai/", "phpunit.xml", "Dockerfile", "docker-entrypoint.sh"],
  except: [".github/workflows/deploy-*.yml"],
  reviewer: "Nicolai",
  tier: TIERS.gate,
};

/** Where code markers are read. A comment line (COMMENT in checks/migrations.mjs) is skipped where the lexer establishes it (commentAt). */
export const MARKER_FILES = {
  extensions: [".php", ".inc", ".js", ".mjs", ".yml", ".yaml"],
  // tools/guardrails quotes every marker as data, and tests and docs only talk about them.
  skip: ["tests/", "e2e/", "doc/", "tools/"],
};

const BOOKS_PROOF = {
  text:
    "A characterization test on the real engine that asserts the rows written and fails on the base, " +
    "with 1250.500, 0.005, a credit line, a foreign currency and zero.",
  e2e: "pr-or-flow",
};

// SQL TRUNCATE, in any case. Not SQL: truncate(...), `$truncate =`, truncate_number(), the CSS class
// in ".truncate", and a class list (class="truncate ...", className: 'truncate').
// Each pattern looks ahead for the keyword before it looks behind, so the look-behinds, which scan back
// over spaces, run only where the keyword is and a long line stays linear.
const NOT_A_CLASS = String.raw`(?<!\bclass(?:Name)?\s*[=:]\s*(?:["'\x60][^"'\x60]{0,300})?)`;
const TRUNCATE_WORDS = String.raw`\s*truncate(?:\s+table)?(?:\s+only)?\s*`;
const TRUNCATE_MARKERS = [
  // The keyword, with or without TABLE and ONLY, then a table name, a quote or a variable, or the line's end.
  new RegExp(String.raw`(?=truncate)${NOT_A_CLASS}\btruncate(?:\s+table)?(?:\s+only)?(?:\s+(?=[\w"'\x60$\{])|\s*$)`, "i"),
  // The keyword alone in a string, the way SQL is built from pieces ('truncate' . ' ' . $table, $sql = "Truncate";),
  // unless it stands alone as an argument, list element or key, or is compared: addClass('truncate'),
  // ['truncate', 'wrap'], 'truncate' => true, case 'truncate':, $mode == 'truncate'.
  new RegExp(
    String.raw`(?=["'\x60]\s*truncate)${NOT_A_CLASS}(?<![=!]=\s*)(?:(?<![(\[,]\s*)(["'\x60])${TRUNCATE_WORDS}\1|(["'\x60])${TRUNCATE_WORDS}\2(?!\s*[)\],]))(?!\s*(?:=>|[=!]=)|:(?!:))`,
    "i",
  ),
  // The upper-case keyword anywhere.
  /\bTRUNCATE\b(?!\s*\()/,
];

// The "password literal" marker: a name that ends in password or passwd, in any case, given a value
// that is not wholly a reference. It counts wherever the name stands: in code, a comment, a string or
// a string handed to a call, in every file. Who reads a string later (a shell, SQL, a connection
// string) is never read.
//
// A small tokenizer per language reads the value: PHP, JavaScript and TypeScript, Python, and the
// shell, environment, YAML and config files of TEXT_FILES. It reads strings by what the language
// interpolates in them, comments as trivia, operators, calls and groups, and the lines a statement
// runs on until it really ends. A value holds no literal only when the reader reads all of it and
// every operand is a reference; whatever it cannot read counts, so the default is to count.
// - A reference is a name with its properties, subscripts and calls (a subscript's key is no value), a
//   constant (null, true, None), a cast, a string that holds only references by what the file's
//   language interpolates in it, and a group, list, map or call whose values are all references.
// - A map's key is no value only where a reader shows what it is: a structure's keys name its fields
//   (a map or list that is all of a name's value, or all of a structure's item), a known reader's
//   keywords name its parameters, and a variable's or a constant's key is a reference. Anywhere else a
//   literal key counts (a number, a quoted key, a JavaScript property name, its shorthand { name } too,
//   and in a call READERS does not know a Python keyword's or a PHP named argument's name): what the
//   map is handed to may return it, Object.keys({...}), array_key_first([...]), ''.join({...}), dict(name=None).
// - A function counts unless it is all of what a name is given: grouped, invoked, handed to a call,
//   joined or read further, its value is what its body returns, which is not read. As all of a helper's
//   value (a verb before the password: validatePassword, check_password, togglePassword) it is no value.
//   As all of any other name's value, its value is what its body returns and assigns: each return, yield
//   and statement's assignment in it, at any depth, holds no literal, or the function counts. A getter, a
//   method and a declaration that a password names (get password() {...}, function getPassword() {...},
//   def password(self): ..., and in JavaScript one whose name is quoted or computed: get 'password'() {...},
//   ['password']() {...}) are read the same way; a declaration without a body gives no value. A body
//   still open where the text ends counts. A function's parameters' defaults are values wherever they
//   stand in its parameter list (function (v = '...'), fn($v = '...') =>, def password(self, v='...')): a
//   call without them returns them. `function` is a function in PHP and JavaScript, and `fn` only as PHP's
//   arrow; anywhere else each is a name.
// - TypeScript's declarations are read whole before their value (tsType): an optional or a definite marker
//   (password?: string = ..., password!: string, password?() {...}), a generic parameter list
//   (function password<T>() {...}, password<T>() {...}, and Python's def password[T](): ...), a whole type before
//   an initializer, a default or a body (Map<string, string>, string & { length: number }, (): T => ... in an
//   arrow), and a member modifier or a decorator before a field (override password: ..., @Input() password: ...).
//   A decorator is read back to its "@" whole, as TypeScript reads one: a name or a group, then any number of
//   member names, calls, type arguments, "!" and tagged templates (@(Dec), @Dec()(), @Dec<T>(), @a?.b!.c()), over
//   lines too (@\nDec(), @Dec\n()), with names written as escapes (@\u0044ec()), and a word, quoted or computed name
//   after it is a member's. Where the line read does not hold the "@", the walk is read again in the lines above it
//   that markerHits was handed; where those do not hold it either and do not start at the file's first line, the
//   name counts as a decorated member's.
//   A type is read whole however deep it nests (tsType keeps what is still to come on a stack, not in recursion),
//   and a line break inside it is read as TypeScript reads one: a union's "|", a conditional's "?" and ":", a
//   function type's "=>", a constraint's extends (infer U\nextends C), abstract\nnew, a path's "." and an import's
//   "(" may stand on the next line; type arguments' "<", a predicate's "is" and "[]" may not. So an arrow's
//   return type that its line ends after is read on with the change's next line (<T>(x: T): abstract\nnew () => R => ...).
//   A conditional's extends clause holds no conditional, as TypeScript reads it, so there infer U extends C reads C
//   as U's constraint (password: T extends infer U extends string ? U : never = ...).
//   A declaration whose type or parameter list the reader cannot read counts. Where a key stands (a field, quoted,
//   computed, #private or decorated, or a parameter), "password: T" may also be an object's key and its value, so
//   such a type counts where an assignment stands in its item; and "(): T" may also be a group and a ternary's ":",
//   so an arrow's return type it cannot read counts where a "=>" follows in its item (itemHolds). An initializer, a
//   default and a "=>" never stand inside a group or a "<...>" list, so none hides from this, and one that stands
//   where "<" and ">" do not balance counts too. Since the reader cannot tell where a type it cannot read ends, a
//   line break never ends such an item: it ends at a ",", a ";", a bracket that closes around it or "?>", so an
//   assignment on a later line counts, also one of the next statement's. A key's method whose
//   return type reads whole but is followed by anything but its body (only not TypeScript does that) is read as a
//   call, as a ternary's branch over lines is. One the text ends inside (a generic
//   list, a type, a method's parameters, or a method's body still to come, as in a method whose "<", type
//   parameters and ">() {...}" stand on three lines, or a getter whose return type starts on the next line) is
//   read on through the change's next lines before it is decided, and counts where they end with it still open, as
//   a value does; where a key stands, a name before "(" or a spaced "<" may be a call's or a comparison's, and is
//   read on only.
// - A call's arguments are values, unless READERS knows the call in the file's language, its form and its
//   receiver: a plain call by its name (getenv(...)); a lookup by name, which returns what its receiver
//   holds under the name and never the name, on any instance (os.environ.get(...), params.get(...),
//   $request->input(...), a PHP page's document.getElementById(...)); a module's function or a global
//   object's method by the receiver's whole path (getpass.getpass, secrets.token_hex, crypto.randomBytes);
//   a transform (split, slice, toString, strip, decode) only on a receiver the table understands, a chain
//   from the runtime's own objects or the environment (window.location.pathname.split('/'),
//   process.env.X.split(','), os.environ['X'].split(',')), what a module function it knows returns, or a
//   Python built-in type (str.strip(x), unbound: its first argument, self, is a value); and a
//   PHP static as Class::method. A name cannot show a module from an instance, and a module may have a
//   function of a transform's name that changes its first argument (codecs.decode(b'...'), shlex.split),
//   so a transform on any other receiver hands its arguments as values. A method read through a class, a
//   namespace or a type (Reflect.get, Foo::get), a call through a variable ($fn(...)) or a namespaced
//   function is no reader. READERS gives each argument its role: what is read (getenv's name, a selector)
//   and an option (an algorithm, an encoding, a prompt), each a string, a number or a reference and never
//   a map, a list or a function; a default (os.getenv's second argument) or a value. No name is a reader by
//   its prefix, nor by its last name alone, and a spread (...args, *args, **kwargs) and every argument
//   after it are values. A shell's command substitution is a call too: only reading a file (cat, a
//   redirect) is a reader.
// - A test of equality (==, ===, !=, <>, instanceof, in, is, not in) at a value's top, beside only what
//   binds tighter than one, is a boolean, whatever its operands hold.
// - A string's literal characters count, and so do its padding and the spaces between two
//   references. A blank string holds none, and a value that is wholly a placeholder (%s, {}, ?, $1,
//   <your password>, an ellipsis, ***; :pw in a string's own text) or an example that names what goes
//   there (your-api-user-password) is none.
// - A number counts, but for 0 or 1 given to a flag's name (use_password).
// - A schema type (varchar(255), required|min:8, a bare INTEGER) or a label (Adgangskode) is none only
//   as a field of the map or array its own declaration names as columns, a schema or rules, or as
//   labels, texts or a language ($expectedColumns, const labels), or of a map or array inside one; a
//   call's arguments and a body's statements have no declaration, whatever holds them. Anywhere
//   else it is a literal. The declaration may stand on a line above: the group a text starts inside is
//   read from the lines above it (outerOf: the change's and the unchanged ones that markerHits hands),
//   and a key at the text's top is a field of that group; a plain assignment there is none.
// - A name gets a value by an assignment (a chain of them is followed), a key's ":" or a quoted key's
//   "=>", a subscript's assignment, define(), a list pattern ([user, pw] = ..., its last name on a
//   line of its own too), a setter (setPassword(...), with spaces, a comment or a line break before its
//   arguments too; set('password', ...), append('password', ...), and the same call opened on a line
//   above), an accessor's declaration (above), and in Python a statement's tuple. A name is a word, a
//   quoted name, or a computed one whose text is static: strings joined with no reference in them
//   ({ ['password']: ... }, cfg['db_' + 'password'] = ..., class C { ['password'] = ... }). A name the
//   line does not spell ('pass' + 'word', a variable's value) is not read. A name is read with its escapes
//   decoded, in every language's way at once (decodeName): a JavaScript identifier's (p\u0061ssword,
//   \u{70}assword), a string's \x, \u, \u{...}, \U, octal and Python's \N{LATIN ... LETTER ...}, and a backslash
//   before a line break, which continues a string over lines; a Python identifier is NFKC-normalized, as Python
//   compares names. A value an escape spells is never a placeholder, a blank or a label: its characters count.
// - A value runs on until its statement ends: at ";", ",", a closing bracket, "?>", an arrow's block
//   body, a word beside it (a shell's or prose's next word), or a line that does not continue it (a
//   line that starts with an operator, "(", "[" or a joining word does). A comment inside it is trivia.
//   A YAML key given nothing takes the more-indented lines after it: a plain scalar's or a list's
//   items are its value, a nested mapping's keys are read on their own lines.
// JavaScript is lexed as JavaScript reads it, in the plain subset (below): a template's interpolations are code
// (scanTemplate) that holds strings, groups, operators and names; a comment, a regular expression or a template in one
// is outside the plain subset, and the template's state is unknown from there. A "/" is a regular expression or a division by the tokens before
// it, as the grammar places it: after an operand (a postfix "++" or TypeScript's x! too) it divides, and after a
// condition's ")" (if, while, for, with), a block's "}", break or continue (a label after one too) or a keyword that is
// no property's name (return, typeof, ...) it opens a regular expression (feed gives each group its kind). A function's
// or a class's body is a block whose "}" a "/" after divides where its keyword stands as an expression's (const n =
// function () {} / 2, after an operator, "(", ",", return, ...) and opens a regular expression where it stands as a
// declaration's (after ";", a block, export default); a type literal after as, satisfies or is is an object's. A line
// break (or a block comment that holds one) reads as automatic semicolon insertion reads it: after a token that ends an
// operand, and after return or yield, it ends the statement where one may end, so a function's keyword after it is a
// declaration's (const n = async<line break>function f() {} /x/, return<line break>class C {} /x/) and a "{" after
// return or yield opens a block. Where those tokens cannot show it (a ")" or "}" whose opening no line
// the entries hold opens, a "}" after a case's ":" or a type's ">", a function's or a class's body whose keyword follows a
// name or a close (@dec class {}), of, await or yield (names in some places), void before a line break (TypeScript's
// type too), a line break that would end a statement in a template's interpolation or a group, a "/" at the top of
// lines whose lines above are not
// known) and a regular expression closes on the line, the lexer's reading is unsure: the line counts, and so does every
// later line of the block that names a password where the two readings may leave the line differently (lineLocal).
// So does a template JavaScript reads no such way: a quote a line break ends inside an interpolation, or a close that
// opens nothing there. Each of these leaves the plain subset.
// A line is lexed where the lines above it leave the lexer: one that starts inside a string or a block comment a
// line above opens (a decorator's template argument, @Dec(`x\ny`) password: ..., a tagged template, a quote continued
// by a backslash, a Python triple-quoted string, a PHP heredoc) is lexed after a lead that puts the lexer there
// (leadOf: the comment's or the string's opening, and for a template the lead the block's lexing recorded at that
// line's start: what its interpolations hold open and their last tokens), from the unchanged and added lines above it
// that markerHits holds (layoutOf), and what follows the string's close on it stands in the group around the string.
// Every line reads on from the block's last two tokens above it (so obj.return<line break>/ 2 divides), a "/", a group's
// opening or close and a "++" that the block's lexing holds at the same place read as the block reads them
// (blockTokens), and a template's lead keeps the function or class keywords that wait for their body there
// (pendingLead), so a line read on its own lexes as the block does. What the string holds on the line
// is also read as the file's code, as a comment's text is (continuedReading), so a string over lines that holds a
// script counts. Where more than LEAD_FRAMES stand open at a line's start (frames, groups and the function and class
// keywords that wait for their body), or the lexer's state stopped being known
// above it, the lexer's state at every later line of the block is unknown, and a line there that names a password
// counts. PHP's '...' and "..." are lexed a line at a time; where the lines a caller hands start below the
// file's first line, their first line is lexed as starting in code.
// Where the text ends before the value does, what follows decides. markerHits hands the spelling's
// readsFrom() the change's lines, and check.mjs hands markerHits each file's added lines between the
// unchanged lines above and below each run (changeset.mjs markerLines: all of them, to the file's end). readsFrom
// reads an added line on through the lines after it, added or unchanged, to the end of the declaration or the
// statement it opens (a method's header renamed above its unchanged body, a name above its
// unchanged value), and a hit is the added line's; where the lines handed end, a complete value has ended, as at a
// file's end. A value still open there, or after WINDOW_LINES lines or WINDOW_CHARS characters, counts. The
// run's first line also reads back over every line above it that markerHits holds (check.mjs hands all of
// them) to a statement one of them starts, however far up, and counts when it goes on with that statement's
// open value and gives it a literal. Where a limit stops it from finding the statement's start, it counts: a
// statement still open past STATEMENT_CHARS characters, more than OPEN_STATEMENTS open around the run, a file's
// runs reading back past READ_BACK_CHARS in all (once per entries and language, so a second caller reads the same
// verdicts: verdictsOf), and, where the caller handed fewer lines (a patch's context, a
// gap), an added literal that goes on with a statement starting above the first line it was handed, or that only a
// bare literal at the top of those lines stands before (startsAbove). A reader with one line only
// (pattern.test) counts a value left open there, and in PHP and JavaScript one whose statement's end is
// not on the line, because the next line may continue it.
//
// A string's own text is read for names too: a name given a value by "=" (a connection string, SQL,
// a shell line) and a quoted key's ":" (JSON). That value runs to a space, ";", "&", ",", a closing
// bracket, "|", "<", ">" or a backslash, segment by segment (a reference, a segment quoted inside),
// and on into what the code joins to the string when the string closes first. A comment's text is
// read as the file's code, and its end ends a value. In every file an environment name in capitals
// given an unquoted word after export, after a comment mark, before a command or alone at its line's
// end is read as a shell word.
//
// In a string, the only characters that are not literal are references, by what the file's language
// interpolates in that string:
// - PHP's "...", `...` and heredocs, and a shell's "...": $name, $name[...], $name->x, {$...}, ${...},
//   $(...) (only when it reads a file: cat or a redirect) and $1; PHP's '...' and a
//   nowdoc interpolate none of them. A JavaScript template reads ${...}, a Python f-string {...}.
// - Every string: an environment name in capitals ($PGPASSWORD), ${...}, and a template or CI
//   expression ({{ x }}, ${{ secrets.X }}).
// An interpolation is a reference only when what it holds is one: ${X:-}, ${X:-$Y}, ${#X}, ${X:0:8} and
// ${X#prefix} are, and a literal shell default, an alternative ${X:+...}, a replacement that puts
// characters in (${X/a/b}), ${12345}, a call handed a literal and an f-string field that its format spec
// pads ({pw:>20}) are not. A format's template handed values (sprintf's, '%s' % pw, '{}'.format(pw)) holds
// no literal only when it is wholly bare fields (%s, %1$s, {}); a width, a fill or a flag pads the value.
//
// The name must stand on the text's first line. Each reader runs in time linear in the text: a token
// is read once, groups, comments and interpolations at most READ_DEPTH deep (one deeper counts), and
// a chain of assignments once.
/** How deep a value's groups, calls, comments and interpolations are read; one deeper counts. */
const READ_DEPTH = 8;
/** How many of the change's next lines, and characters in all, one statement may run on before it counts. */
const WINDOW_LINES = 40;
const WINDOW_CHARS = 20000;
/** How many characters of the lines above a run a statement may hold, read from its start, before it counts. */
const STATEMENT_CHARS = 200000;
/** How many statements still open around a run are read on, nearest first, before the run counts. */
const OPEN_STATEMENTS = READ_DEPTH;
/**
 * How many characters the runs of one file's entries may read back over, in all, per reading, before a run counts:
 * each run reads its statement again from its start, so many runs below one long open statement would cost their
 * count times its length. A reading is verdictsOf's one pass over the entries per language, so asking again never
 * spends it twice.
 */
const READ_BACK_CHARS = 2000000;

/** A name that ends in password or passwd, in any case. */
const PASSWORD_NAME = /pass(?:word|wd)$/i;
const PASSWORD_MENTION = /pass(?:word|wd)/i;
/** `text` with A to Z lowercased and nothing else, as HTML folds a tag's name: one character for one, so every offset stands (U+0130 lowercases to two). */
const asciiLower = (text) => text.replace(/[A-Z]+/g, (upper) => upper.toLowerCase());
/** A flag's name: 0 or 1 given to it is a switch (use_password: 0), not a password. */
const FLAG_NAME = /^[$@]*(?:use|has|is|show|need|needs|require|required|no|skip|reset|change|check|ask|enable|disable|save|send|remember|allow|force|with|without)_?pass(?:word|wd)$/i;

/** How a value ran to the text's end: before any operand, open in itself, or complete with its statement's end not seen. */
const EMPTY = "empty";
const OPEN = "open";
const UNENDED = "unended";

/**
 * How each language lexes and reads. `statements`: a statement ends at ";", so a line without one may go on. PHP has
 * no lexer here: its tokens are PHP's own (PHP's tokens, below), so it says only how its code reads.
 */
const LANGUAGES = {
  php: { id: "php", statements: true, join: "." },
  js: { id: "js", quotes: { "'": "script", '"': "script", "`": "template" }, slash: true, block: true, regex: true, statements: true, join: "+" },
  py: { id: "py", quotes: { "'": "literal", '"': "literal" }, hash: true, triple: true, prefixes: true, lineJoin: true, join: "+" },
};

const OPERATORS = [
  "<<<", "===", "!==", "**=", "...", "<<=", ">>=", ">>>", "??=", "||=", "&&=", "<=>", "?->", "//=",
  "==", "!=", "<=", ">=", "&&", "||", "??", "?.", "->", "=>", "::", "**", "++", "--", "+=", "-=", "*=", "/=", "%=", ".=",
  "&=", "|=", "^=", "<<", ">>", ":=", "<>", "//",
  "+", "-", "*", "/", "%", "=", "<", ">", "!", "~", "^", "&", "|", "?", ":", ".", "@",
];
const ASSIGN = new Set(["=", ".=", "+=", "-=", "*=", "/=", "%=", "**=", "??=", "||=", "&&=", "|=", "&=", "^=", "<<=", ">>=", ":=", "//="]);
/** Operators that may stand before an operand. */
const PREFIX = new Set(["!", "-", "+", "~", "@", "&", "*", "**", "...", "++", "--"]);
/** Words that join two operands, or stand before one. */
const JOINING = new Set(["or", "and", "not", "xor", "if", "else", "in", "is", "as", "instanceof", "satisfies", "new", "await", "typeof", "void", "delete", "yield", "async", "clone", "lambda", "for", "of", "keyof"]);
/** Of those, the ones that start an operand: a line that starts with one does not continue the line before. */
const STARTING = new Set(["new", "await", "typeof", "void", "delete", "yield", "async", "clone", "not", "lambda"]);
const PHP_CASTS = new Set(["string", "int", "integer", "bool", "boolean", "float", "double", "real", "array", "object", "binary", "unset"]);
/** Words before "name:" that make the colon a declaration's type, not a ternary's branch. */
const DECLARING = new Set(["let", "const", "var", "readonly", "public", "private", "protected", "static", "declare"]);

/**
 * The calls whose arguments are not all values, per language and by the form of the call and its receiver.
 * A role per argument, in order: "n" names what is read and "o" is an option (a string, a number or a
 * reference; no map, list or function), "d" a default and "v" a value (neither may hold a literal), "f" a
 * format's template (only its placeholders and references are no literal); "+" repeats the role before it,
 * and an argument past the roles is a value. `keywords` gives Python's keyword arguments by the method's name.
 * - `calls`: a plain call by its name (getenv(...), PHP's \getenv(...) too).
 * - `lookups`: a method that reads what its receiver holds under a name ($request->input(...),
 *   document.querySelector(...), os.environ.get(...), params.get(...)). It returns what is held, never the
 *   name, so it is a reader on any receiver but a class, a type or a namespace (Reflect.get, Foo.get), whose
 *   method is a function of its arguments. PHP's "->" is read on an object only, so its methods are all here.
 * - `members`: a module's function or a global object's method by the receiver's whole path of names
 *   (os.urandom, getpass.getpass, secrets.token_hex, crypto.randomBytes, window.prompt).
 * - `transforms`: a method that gives its receiver's value changed by its options (split, slice, toString,
 *   strip, decode). A module may have a function of the same name that changes its first argument instead
 *   (codecs.decode(b'...'), shlex.split('...'), os.path.split('...')), and a name cannot show a module from an
 *   instance, so a transform is read only on a receiver the table understands: a chain whose names, up to its
 *   first call or subscript, are one of `roots` or go on from one (the runtime's own objects and the
 *   environment: window.location.pathname.split('/'), process.env.X.split(','), os.environ['X'].split(',')),
 *   what a member returns (crypto.randomBytes(16).toString('hex')), or, through `typed`, a built-in type
 *   (str.strip(x), unbound: its first argument is self, a value).
 * - `statics`: PHP's "Class::method".
 * Every other call hands its arguments as values: no name is a reader by its prefix, nor by its last name alone.
 * A call through a variable ($fn(...)) or a namespaced PHP function is a call no table knows.
 */
const READERS = {
  php: {
    calls: {
      // The environment, a request and Saldi's readers: if_isset($array, $default, $key), ifset($array, $key, $default),
      // get_settings_value($name, $group, $default, ...).
      getenv: "nd", env: "nd", config: "nd", session: "nd", filter_input: "onov",
      if_isset: "vdn", ifset: "vnd", get_settings_value: "nndvvo", check_and_sanitize_input: "nov",
      // Transformations of a value, and generators.
      trim: "vo", rtrim: "vo", ltrim: "vo", md5: "vo", sha1: "vo", hash: "ovo", hash_hmac: "ovvo", password_hash: "vov",
      base64_decode: "vo", htmlspecialchars: "voo", htmlentities: "voo", mb_convert_encoding: "voo", substr: "voo", mb_substr: "vooo",
      random_bytes: "o", uniqid: "vo", rand: "oo", mt_rand: "oo",
      // A format: its template's literal characters count ("f"), its values are values. Splitting: the separator is an option.
      sprintf: "fv+", printf: "fv+", vsprintf: "fv", explode: "ovo", preg_split: "ovoo", str_split: "vo",
    },
    lookups: { input: "nd", post: "nd", get: "nd", query: "nd", param: "nd", cookie: "nd", session: "nd", header: "nd" },
    members: {},
    transforms: {},
    typed: {},
    roots: [],
    // The tests' own credential: a password made at random per process for the account a suite seeds under the key;
    // Laravel's hash and random string.
    statics: { "TestCredentials::password": "n", "Hash::make": "vo", "Str::random": "o" },
    keywords: {},
  },
  js: {
    calls: { prompt: "od", require: "n", import: "n", $: "n", jQuery: "n" },
    lookups: { get: "n", getItem: "n", getElementById: "n", querySelector: "n", getElementsByName: "n", getAttribute: "n", attr: "n", prop: "n" },
    members: { "window.prompt": "od", "crypto.randomBytes": "o", "crypto.randomInt": "oo", "Buffer.from": "vo" },
    transforms: { toString: "o", slice: "oo", substring: "oo", split: "oo" },
    typed: {},
    roots: ["window", "document", "location", "navigator", "process.env"],
    statics: {},
    keywords: {},
  },
  py: {
    calls: { getenv: "nd", input: "o", getpass: "o", token_hex: "o", token_urlsafe: "o", quote: "vo", quote_plus: "vo" },
    lookups: { getenv: "nd", get: "nd", pop: "nd" },
    members: {
      "getpass.getpass": "o", "secrets.token_hex": "o", "secrets.token_urlsafe": "o", "os.urandom": "o",
      "urllib.parse.quote": "vo", "urllib.parse.quote_plus": "vo", "parse.quote": "vo", "parse.quote_plus": "vo",
    },
    transforms: { strip: "o", lstrip: "o", rstrip: "o", decode: "oo", encode: "oo", split: "oo" },
    typed: { strip: "o", lstrip: "o", rstrip: "o", decode: "oo", encode: "oo", split: "oo", get: "nd", pop: "nd" },
    roots: ["os.environ", "os.getenv"],
    statics: {},
    keywords: {
      getenv: { key: "n", default: "d" }, get: { key: "n", default: "d", fallback: "d" }, pop: { key: "n", default: "d" },
      getpass: { prompt: "o", stream: "o" }, input: { prompt: "o" }, decode: { encoding: "o", errors: "o" }, encode: { encoding: "o", errors: "o" },
      quote: { safe: "o", encoding: "o", errors: "o" }, quote_plus: { safe: "o", encoding: "o", errors: "o" }, token_hex: { nbytes: "o" }, token_urlsafe: { nbytes: "o" },
    },
  },
};
/** Python's built-in types: a method read through one is unbound, and its first argument is self. */
const PY_TYPES = new Set(["str", "bytes", "bytearray", "list", "dict", "set", "frozenset", "tuple", "object", "int", "float", "memoryview", "type"]);
/** JavaScript's namespaces, and lodash's: their methods are functions of their arguments, never an instance's. */
const JS_NAMESPACES = new Set(["Reflect", "Object", "JSON", "Math", "Atomics", "Intl", "Proxy", "Promise", "Array", "String", "Number", "Symbol", "BigInt", "Date", "Buffer", "URL", "_", "lodash"]);
/** A class's name (CamelCase, with a lower-case letter): a method read through it is no instance's. */
const CLASS_NAME = /^[A-Z][A-Za-z0-9_]*[a-z][A-Za-z0-9_]*$/;
/** A call no table knows: every argument is a value, and a keyword's literal name counts. */
const UNKNOWN_CALL = Object.freeze({ roles: "", keywords: {}, known: false });

/** Words in a declaration's name that make its fields a schema, or labels: bound to that declaration and what it holds. */
const SCHEMA_DECLARATION = /column|(?<![a-z])cols?(?![a-z])|schema|rule|validat|field/i;
const LABEL_DECLARATION = /label|title|placeholder|caption|hint|message|msg|tekst|(?<![a-z])texts?(?![a-z])|lang|translation|i18n/i;
const SCHEMA_TYPE = "varchar|char|text|string|int|integer|bigint|smallint|serial|boolean|bool|bytea|blob|json|jsonb|uuid";
const SCHEMA_RULE = String.raw`(?:required|nullable|sometimes|confirmed|string|min:\d{1,9}|max:\d{1,9})`;
const SCHEMA_CONSTRAINT = String.raw`\s+(?:not\s+null|null|unique|primary\s+key)`;
/** A column type, maybe with its size and constraints, or a validation rule list. */
const SCHEMA_VALUE = new RegExp(String.raw`^(?:(?:${SCHEMA_TYPE})(?:\s*\(\s*\d{1,9}(?:\s*,\s*\d{1,9})?\s*\))?(?:${SCHEMA_CONSTRAINT}){0,4}|${SCHEMA_RULE}(?:\|${SCHEMA_RULE}){0,20})$`, "i");
/** A label that names a password: a few words around a password word, maybe a closing mark. */
const LABEL_VALUE = /^(?:[A-Za-zÆØÅæøåÄÖÜäöü]{1,40} ){0,8}(?:password|passwd|passord|passwort|adgangskode|kodeord)(?: [A-Za-zÆØÅæøåÄÖÜäöü]{1,40}){0,8}[:?.!]?$/i;
/** A value that is wholly a placeholder: a printf conversion, a format field, a SQL parameter, angle brackets, an ellipsis or a mask. */
const PLACEHOLDER = /^(?:%(?:\(\w{1,40}\))?(?:\d{1,3}\$)?[-+ 0#]{0,8}(?:[1-9]\d{0,8})?(?:\.\d{1,8})?[bcdeEfFgGosuxX]|<[^<>]{0,200}>|\{\d?\}|\.\.\.|…|\*{3,}|\?|\$\d)$/;
/** A named SQL parameter (:pw): a placeholder only in a string's own text (SQL), where a name is given it; a whole value spelled so is a word. */
const SQL_PARAMETER = /^:[A-Za-z_]\w{0,40}$/;
/** An example that names what goes there: your-api-user-password, dit_kodeord. */
const EXAMPLE = /^(?:your|din|dit)[-_ ][A-Za-zÆØÅæøå_ -]{0,200}$/i;
/** An unquoted constant in a text file. */
const TEXT_CONSTANT = /^(?:null|none|nil|undefined|true|false|~)$/i;

/**
 * A format template's own fields, which take a value as it is: %s, %1$s, %(pw)s, %.2f, {}, {0}. A width, a
 * fill or a flag (%20s, %-8s, {:>20}) pads the value with literal characters, so a template that has one counts.
 */
const TEMPLATE_FIELD = /(%(?:\(\w{1,40}\))?(?:\d{1,3}\$)?(?:\.\d{1,8})?[bcdeEfFgGosuxX]|\{\d?\})/;
/** Whether `text` is wholly template fields (one at least), with no literal character between them. */
const bareTemplate = (text) => text.length <= 2000 && text.split(TEMPLATE_FIELD).every((piece, n) => (n % 2 === 1 ? true : !piece)) && TEMPLATE_FIELD.test(text);

const isPlaceholder = (text, inText = false) => text.length <= 300 && (PLACEHOLDER.test(text) || EXAMPLE.test(text) || (inText && SQL_PARAMETER.test(text)));

// ----- Line terminators -------------------------------------------------------------------------
//
// Where a line ends, defined once: every reader here, and changeset.mjs, asks this section.
//
// A change's lines are git's. Git ends a line at LF (LINE_END), and the CR of a CR LF is part of that terminator,
// not of the line's text (lineText, fileLines: changeset.mjs splits a file and hands its added lines by them, and
// readsFrom takes the entries it is handed so), unless the line's own text ends in a CR: then the line keeps it, so
// that no CR of the line's joins the LF into a CR LF. A reader joins the lines it reads with LINE_END, which gives the
// file's text back as the languages here read it (where a CR LF ends a line, an LF ends it alike), and a text's first
// line, which a password's name must stand on (firstLine, logicalFirstLine, reader's firstEnd), is the change's line:
// a hit is a line of the diff.
//
// LF, and a CR LF, are the only line terminators the readers read (breakAt): the plain subset's (below). Any other a
// language knows (a bare CR, U+0085, U+2028, U+2029: UNUSUAL_TERMINATOR) puts the line outside the plain subset, where
// the readers do not read it at all: a line there that names a password counts, and a code marker reads it whole.

/** Where git ends a change's line, and what a reader joins the lines it reads with. */
export const LINE_END = "\n";

/** The length of the line break at `text[i]`: 2 for a CR LF, 1 for an LF, 0 where none stands. */
const breakAt = (text, i) => (text[i] === "\n" ? 1 : text[i] === "\r" && text[i + 1] === "\n" ? 2 : 0);

/** Where the first line break (breakAt) at or after `from` stands in `text`, or -1. */
function nextBreak(text, from = 0) {
  const nl = text.indexOf(LINE_END, from);
  return nl > from && text[nl - 1] === "\r" ? nl - 1 : nl;
}

/**
 * The line terminators other than LF and a CR LF that some language here reads (a bare CR: PHP, Python, JavaScript,
 * YAML 1.2; U+2028 and U+2029: JavaScript, YAML 1.1; U+0085: YAML 1.1). A text that holds one is outside the plain subset.
 */
const UNUSUAL_TERMINATOR = /\r(?!\n)|[\u0085\u2028\u2029]/;

/** Whether `text` holds a line terminator other than LF and a CR LF (UNUSUAL_TERMINATOR): it is no plain text. */
export const holdsUnusualTerminator = (text) => UNUSUAL_TERMINATOR.test(text);

/**
 * A change's line's text: the line without the CR of its CR LF (at a file's end without one, a CR is its own
 * terminator). A line whose own text ends in a CR keeps it (a\r\r stays a\r\r): without it the line's CR would stand
 * before the LF a reader joins the lines with, and make one CR LF of the two terminators the file holds, a bare CR that
 * puts the line outside the plain subset gone. So lines joined at LINE_END read as the file does, and a line given
 * lineText twice (addedWithContext, then ownLines) is the line given it once: lineText(lineText(x)) === lineText(x).
 */
export const lineText = (line) => (line.endsWith("\r") && !line.endsWith("\r\r") ? line.slice(0, -1) : line);

/** A file's lines as git numbers them, each without its terminator: fileLines(content)[n - 1] is line n. */
export const fileLines = (content) => content.split(LINE_END).map(lineText);

/** The first line of `text`: the change's line it starts with (LINE_END). */
const firstLine = (text) => {
  const nl = text.indexOf(LINE_END);
  return nl < 0 ? text : text.slice(0, nl);
};

// ----- Strings ----------------------------------------------------------------------------------

/**
 * The index after the bracket that closes the one at `i` ("{", "(" or "["), skipping quoted text and
 * JavaScript templates, or -1. A character of `fence` (the quote of the string the bracket stands in)
 * ends the search outside a nested string, and so does a line break that breaks a quote.
 * Iterative and linear.
 */
function closeOf(text, i, fence = "") {
  const stack = [];
  for (let j = i; j < text.length; j++) {
    const c = text[j];
    const top = stack[stack.length - 1];
    if (c === "\\") {
      j++;
      continue;
    }
    if (top === "'" || top === '"') {
      if (c === top) stack.pop();
      else if (breakAt(text, j)) return -1;
      continue;
    }
    if (top === "`") {
      if (c === "`") stack.pop();
      else if (c === "$" && text[j + 1] === "{") {
        stack.push("}");
        j++;
      }
      continue;
    }
    if (stack.length && fence.includes(c)) return -1;
    if (c === "'" || c === '"' || c === "`") stack.push(c);
    else if (c === "(") stack.push(")");
    else if (c === "[") stack.push("]");
    else if (c === "{") stack.push("}");
    else if (c === ")" || c === "]" || c === "}") {
      if (stack.pop() !== c) return -1;
      if (!stack.length) return j + 1;
    }
  }
  return -1;
}

/** A shell word that names a file: unquoted, or quoted in parts, with no operator, redirect or substitution in it. */
const SHELL_PATH = String.raw`(?:[^\s|;&<>\x60()'"\\]|"[^"\x60\\]*"|'[^']*')+`;
/** A command substitution that reads: cat or a redirect handed only file names (cat -- "$DIR/db", < /run/secrets/db). */
const COMMAND_READS = new RegExp(String.raw`^\s*(?:cat(?:\s+-[A-Za-z-]{1,20}){0,4}\s+${SHELL_PATH}(?:\s+${SHELL_PATH}){0,20}|<\s*${SHELL_PATH})\s*$`);

/**
 * Whether a command substitution's output is a reference: its command is a call, and only reading a file is a
 * reader. Any other command is handed its words as values (echo and printf print them, a here-string feeds one,
 * base64, openssl or an interpreter turn one into the output), so its output counts.
 */
const commandReads = (inner) => inner.length <= 2000 && COMMAND_READS.test(inner);

/** ${...}'s inside: a shell parameter, an empty or reference default, or ${X:?message}; anything else is read as code. */
function bracedReference(inner, language, depth) {
  return shellBraced(inner) ?? expressionIsReference(inner, language, depth);
}

/** Whether ${...}'s inside is a shell expansion that adds no literal (true), one that adds one (false), or no shell expansion (null). */
function shellBraced(inner) {
  if (/^\s*(?:[A-Za-z_]\w*|[0-9@*#?!$-])\s*$/.test(inner)) return true;
  // A shell expansion that adds no character: its length, a substring, a removed prefix or suffix, a case change.
  if (/^(?:#[A-Za-z_]\w*|[A-Za-z_]\w*(?::\s*-?\d{1,9}(?::\s*-?\d{1,9})?|##?[^}/]{0,200}|%%?[^}/]{0,200}|\^\^?|,,?))$/.test(inner)) return true;
  // A replacement (${X/a/b}, ${X//a/b}) puts its own characters in: none, or a reference, adds no literal.
  const replace = /^([A-Za-z_]\w*)\/\/?[^/]{0,200}(?:\/([\s\S]*))?$/.exec(inner);
  if (replace) return replace[2] === undefined || /^(?:|\$\w+|\$\{\w+\})$/.test(replace[2]);
  const shell = /^([A-Za-z_]\w*):?([-=?+])([\s\S]*)$/.exec(inner);
  if (shell) {
    if (shell[2] === "?") return true;
    if (shell[2] === "+") return false;
    return /^(?:|\$\w+|\$\{\w+\})$/.test(shell[3]);
  }
  return null;
}

/**
 * The string readings (stringParts) in which a backslash before a line break continues the string over it, the two
 * adding nothing: JavaScript's quotes (script) and templates, Python's quotes and f-strings and a text format's '...'
 * (literal, fstring), and a shell's "..." and a text format's (shell). PHP's '...' and "..." (plain, dollar), its heredocs
 * and nowdocs keep the backslash and the line break as the string's text.
 */
const CONTINUES = new Set(["script", "template", "literal", "fstring", "shell"]);

/**
 * The parts of a string body by `reading`: {lit} text, {ref} a reference, {bad} an interpolation that
 * holds a literal. "plain" (PHP's '...' and a nowdoc), "script" (JavaScript's quotes) and "literal" (Python's quotes, a
 * text format's '...') interpolate only an environment name in capitals, ${...} and {{ ... }}; "dollar" (the text of a
 * PHP "...", backtick string or heredoc, between PHP's own interpolations: phpParts, so no $name, {$...} or ${...} PHP
 * reads is left in it) and "shell" (a shell's "...", a text format's) also $name, {$...}, $(...) and $1; "template"
 * reads ${...} as
 * JavaScript and "fstring" {...} as Python. A backslash continues the string over a line break where CONTINUES
 * holds its reading. `fence` is the string's own quote, which an interpolation never crosses; a template's interpolation is read
 * as scanTemplate reads it, so it may hold a template of its own. A numeric escape (\x61, \u0061, \u{61}, \141,
 * \N{LATIN SMALL LETTER A}) is read as its character in every reading, as some language reads it, and marks its part
 * `esc`: the value it spells is never a placeholder, a blank or a label (partsValue).
 */
function stringParts(body, reading, fence, depth) {
  const parts = [];
  let lit = "";
  let esc = false;
  const flush = () => {
    if (lit) parts.push(esc ? { lit, esc } : { lit });
    lit = "";
    esc = false;
  };
  const push = (reference) => {
    flush();
    parts.push(reference ? { ref: true } : { bad: true });
  };
  // A shell's "..." interpolates as PHP's "..." does; the host language reads what ${...} holds. No PHP is read here
  // (expressionIsReference), so a ${...} in a PHP string that is no shell parameter holds a literal.
  const dollar = reading === "dollar" || reading === "shell";
  const host = reading === "fstring" ? "py" : dollar || reading === "plain" ? "php" : "js";
  const continues = CONTINUES.has(reading);
  const deeper = depth < READ_DEPTH;
  const n = body.length;
  let i = 0;
  while (i < n) {
    const c = body[i];
    if (c === "\\") {
      const next = body[i + 1];
      const escape = next === undefined ? null : numericEscape(body, i);
      if (escape) {
        // A part of its own, so that what an escape spells is known character by character (escapedMask).
        flush();
        lit = escape.ch;
        esc = true;
        flush();
        i += escape.length;
        continue;
      }
      // A backslash before a line break, where its reading continues over one (CONTINUES), continues the string: nothing.
      const continued = continues ? breakAt(body, i + 1) : 0;
      if (next === undefined) lit += c;
      else if (reading === "plain") lit += next === "'" || next === "\\" ? next : c + next;
      else if (!continued) lit += next === "n" ? "\n" : next === "t" ? "\t" : next === "r" ? "\r" : next;
      i += 1 + (continued || 1);
      continue;
    }
    // ${{ ... }} and {{ ... }}: a template or CI expression (in an f-string "{{" is a brace).
    const ci = c === "$" && body.startsWith("${{", i) ? i + 1 : c === "{" && reading !== "fstring" && body.startsWith("{{", i) ? i : -1;
    if (ci >= 0) {
      const close = body.indexOf("}}", ci + 2);
      if (close >= 0) {
        push(deeper && expressionIsReference(body.slice(ci + 2, close), "js", depth + 1));
        i = close + 2;
        continue;
      }
    }
    if (c === "$") {
      const next = body[i + 1] ?? "";
      if (next === "{") {
        const close = reading === "template" ? interpolationEnd(body, i) : closeOf(body, i + 1, fence);
        if (close < 0) {
          push(false);
          break;
        }
        push(deeper && bracedReference(body.slice(i + 2, close - 1), host, depth + 1));
        i = close;
        continue;
      }
      if (dollar) {
        if (next === "(") {
          const close = body.indexOf(")", i + 2);
          if (close < 0) {
            push(false);
            break;
          }
          push(commandReads(body.slice(i + 2, close)));
          i = close + 1;
          continue;
        }
        const name = /^\$[A-Za-z_\u0080-\uffff][\w\u0080-\uffff]*/.exec(body.slice(i, i + 400));
        if (name) {
          let j = i + name[0].length;
          for (;;) {
            if (body[j] === "[") {
              const close = body.indexOf("]", j);
              if (close < 0 || body.slice(j, close).includes(LINE_END)) break;
              j = close + 1;
            } else if (body.startsWith("->", j) && /[A-Za-z_]/.test(body[j + 2] ?? "")) {
              j += 2;
              while (j < n && /\w/.test(body[j])) j++;
            } else break;
          }
          push(true);
          i = j;
          continue;
        }
        if (/[0-9@*#?!$]/.test(next) && !/\w/.test(body[i + 2] ?? "")) {
          push(true);
          i += 2;
          continue;
        }
      } else {
        const env = /^\$[A-Z_][A-Z0-9_]*(?![A-Za-z0-9_])/.exec(body.slice(i, i + 200));
        if (env) {
          push(true);
          i += env[0].length;
          continue;
        }
      }
    } else if (c === "{" && dollar && body[i + 1] === "$") {
      const close = closeOf(body, i, fence);
      if (close < 0) {
        push(false);
        break;
      }
      push(deeper && expressionIsReference(body.slice(i + 1, close - 1), "php", depth + 1));
      i = close;
      continue;
    } else if (reading === "fstring" && (c === "{" || c === "}")) {
      if (body[i + 1] === c || c === "}") {
        lit += c;
        i += body[i + 1] === c ? 2 : 1;
        continue;
      }
      const close = closeOf(body, i, fence);
      if (close < 0) {
        push(false);
        break;
      }
      const field = body.slice(i + 1, close - 1);
      const expression = fieldExpression(field);
      push(deeper && bareFormatSpec(field.slice(expression.length)) && expressionIsReference(expression, "py", depth + 1));
      i = close;
      continue;
    }
    lit += c;
    i++;
  }
  flush();
  return parts;
}

/**
 * Whether an f-string field's conversion and format spec (what follows its expression) take the value as it is:
 * none, a conversion (!r, !s, !a), a precision or a type (:.2f, :d). A fill, an alignment, a sign or a width
 * (:>20, :*^30, :+, :08) pads it with literal characters, and a nested field ({pw:{w}}) is not read.
 */
const bareFormatSpec = (rest) => /^(?:![rsa])?(?::(?:\.\d{1,8})?[bcdeEfFgGnosxX%]?)?$/.test(rest);

/** An f-string field without its conversion (!r) and format spec (:>10): the expression. */
function fieldExpression(field) {
  let depth = 0;
  for (let i = 0; i < field.length; i++) {
    const c = field[i];
    if (c === "'" || c === '"') {
      const end = field.indexOf(c, i + 1);
      if (end < 0) return field;
      i = end;
    } else if ("([{".includes(c)) depth++;
    else if (")]}".includes(c)) depth--;
    else if (depth === 0 && ((c === "!" && field[i + 1] !== "=") || c === ":")) return field.slice(0, i);
  }
  return field;
}

/** A string token's parts, read the way `reading` reads them, once per reading; a PHP string's by PHP's own tokens (phpParts). */
function partsOf(tok, reading = tok.reading) {
  if (tok.php) return (tok.phpParts ??= phpParts(tok));
  tok.cache ??= {};
  return (tok.cache[reading] ??= stringParts(tok.body, reading, tok.fence, tok.depth));
}

/** What parts hold: { bad, refs, text, esc } (text: the literal characters; esc: an escape spells some of them). */
function partsValue(parts) {
  let text = "";
  let refs = 0;
  let esc = false;
  for (const part of parts) {
    if (part.bad) return { bad: true, refs, text, esc };
    if (part.ref) refs++;
    else {
      text += part.lit;
      if (part.esc) esc = true;
    }
  }
  return { bad: false, refs, text, esc };
}

/** Which characters of flatten(parts) an escape spells (stringParts' `esc` parts), as a mask over them, or null where none does. */
function escapedMask(parts) {
  if (!parts.some((part) => part.esc)) return null;
  const mask = [];
  for (const part of parts) {
    const length = part.ref || part.bad ? 1 : part.lit.length;
    for (let j = 0; j < length; j++) mask.push(part.esc ? 1 : 0);
  }
  return Uint8Array.from(mask);
}

/** Whether an escape spells any of characters [s, e) of a flattened text (escapedMask's `mask`, or null). */
const spelledBy = (mask, s, e) => mask !== null && mask.subarray(s, e).includes(1);

/** Parts as one text, a reference as \u0000 and a bad interpolation as \u0001. */
function flatten(parts) {
  let flat = "";
  for (const part of parts) flat += part.ref ? "\u0000" : part.bad ? "\u0001" : part.lit;
  return flat;
}

// ----- The code lexer ---------------------------------------------------------------------------

const WORD = {
  // An identifier, maybe #private, whose characters may be written as escapes (Dec, \u{44}ec, \u{00000044}ec).
  js: /#?(?:[A-Za-z_$\u0080-\uffff]|\\u[0-9A-Fa-f]{4}|\\u\{0*[0-9A-Fa-f]{1,6}\})(?:[\w$\u0080-\uffff]|\\u[0-9A-Fa-f]{4}|\\u\{0*[0-9A-Fa-f]{1,6}\})*/y,
  py: /[A-Za-z_\u0080-￿][\w\u0080-￿]*/y,
};
const NUMBER = /\d[\w.]*|\.\d[\w.]*/y;
const PY_PREFIX = /(?:[rRbBuUfF]|[rR][bBfF]|[bBfF][rR])(?=['"])/y;
/** After these words a "/" opens a regular expression literal, unless the word is a property's name (a.return / 2). */
const REGEX_AFTER = new Set(["return", "typeof", "case", "do", "else", "in", "of", "new", "delete", "void", "throw", "yield", "await"]);
/** The words whose "(" holds a statement's condition: a "/" after its ")" opens a regular expression (if (a) /x/.test(s)). */
const CONDITION_WORDS = new Set(["if", "while", "for", "with"]);
/** The words a "{" after opens a block, not an object: so does a name (class C {, extends B {, a return type's). */
const BLOCK_WORDS = new Set(["else", "do", "try", "finally"]);

function sticky(regex, text, i) {
  regex.lastIndex = i;
  const m = regex.exec(text);
  return m ? m[0] : null;
}

// ----- Escapes ----------------------------------------------------------------------------------

/** A numeric escape: \xHH (PHP's \xH too), \u{...}, \uHHHH, \UHHHHHHHH, Python's \N{LATIN ... LETTER X}, or octal \NNN. */
const NUMERIC_ESCAPE = new RegExp(
  String.raw`\\(?:x([0-9A-Fa-f]{1,2})|u\{0*([0-9A-Fa-f]{1,6})\}|u([0-9A-Fa-f]{4})|U([0-9A-Fa-f]{8})|N\{([^}\r\n]{1,100})\}|([0-7]{1,3}))`,
  "y",
);
const LATIN_LETTER = /^\s*LATIN\s+(SMALL|CAPITAL)\s+LETTER\s+([A-Z])\s*$/i;

/**
 * The numeric escape at `text[i]` ("\\") as { ch, length }, or null where none starts there. A control character, which
 * no name holds and which the readers' markers (\u0000, \u0001) could be taken for, is read as U+FFFD; so is a named
 * character other than a Latin letter.
 */
function numericEscape(text, i) {
  NUMERIC_ESCAPE.lastIndex = i;
  const m = NUMERIC_ESCAPE.exec(text);
  if (!m) return null;
  const hex = m[1] ?? m[2] ?? m[3] ?? m[4];
  let code;
  if (hex !== undefined) code = parseInt(hex, 16);
  else if (m[5] !== undefined) {
    const letter = LATIN_LETTER.exec(m[5]);
    code = letter ? (letter[1].toLowerCase() === "small" ? letter[2].toLowerCase() : letter[2].toUpperCase()).charCodeAt(0) : 0xfffd;
  } else code = parseInt(m[6], 8);
  if (code > 0x10ffff) return null;
  return { ch: code < 0x20 || code === 0x7f || (code >= 0xd800 && code <= 0xdfff) ? "�" : String.fromCodePoint(code), length: m[0].length };
}

/**
 * A name's text with its escapes read, in every language's way at once (a name compared with a password's never
 * depends on which language wrote it): numeric escapes (numericEscape), a line continued by a backslash ("\" and a
 * line break: nothing), and any other escaped character as itself (\' \\ \p). An identifier's escapes are JavaScript's:
 * password and \u{70}assword are password. `places`, where given, gets two numbers per UTF-16 unit of the result: the
 * start and the end in `text` of what it was read from (nameSpans).
 */
function decodeName(text, places = null) {
  if (!places && !text.includes("\\")) return text;
  let out = "";
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (c !== "\\") {
      out += c;
      places?.push(i, i + 1);
      continue;
    }
    const escape = numericEscape(text, i);
    if (escape) {
      out += escape.ch;
      if (places) for (let u = 0; u < escape.ch.length; u++) places.push(i, i + escape.length);
      i += escape.length - 1;
      continue;
    }
    // A line break (breakAt), CR LF one.
    const continued = breakAt(text, i + 1);
    if (continued) i += continued;
    else if (text[i + 1] !== undefined) {
      out += text[i + 1];
      places?.push(i, i + 2);
      i++;
    }
  }
  return out;
}

/** A word's name as its language reads it: JavaScript's escapes decoded (decodeName), Python's identifier NFKC-normalized, as Python compares names. */
function wordName(word, L) {
  if (L.id === "js" && word.includes("\\")) return decodeName(word);
  if (L.id === "py" && /[^\x00-\x7f]/.test(word)) return word.normalize("NFKC");
  return word;
}

/**
 * Whether `text` names a password: as written, or once escapes are read (decodeName) and, as Python reads a name,
 * NFKC-normalized. A prefilter: the readers then read the name where it stands.
 */
function mentions(text) {
  if (PASSWORD_MENTION.test(text)) return true;
  if (!/[\\\u0080-￿]/.test(text)) return false;
  const decoded = decodeName(text);
  return PASSWORD_MENTION.test(decoded) || PASSWORD_MENTION.test(decoded.normalize("NFKC"));
}

/** The password words' proper prefixes, lowercase: a text that ends in one and a backslash may name a password on its next line. */
const NAME_STARTS = ["password", "passwd"].flatMap((w) => Array.from({ length: w.length - 1 }, (_, k) => w.slice(0, k + 1)));

/** Whether `line` (a change's line, maybe still with its CR LF's CR: lineText) ends with a backslash that continues it (an odd run of them): a line break escaped. */
function lineContinues(line) {
  const e = lineText(line).length;
  let k = e;
  while (k > 0 && line[k - 1] === "\\") k--;
  return (e - k) % 2 === 1;
}

/**
 * The text's first line, with the lines a backslash at its end continues joined to it ("\" and the line break kept,
 * which decodeName reads as nothing), so a name a string spells over lines ('pass\<line break>word') stands on it.
 */
function logicalFirstLine(text) {
  let start = 0;
  let end = text.indexOf(LINE_END);
  while (end >= 0 && lineContinues(text.slice(start, end))) {
    start = end + 1;
    end = text.indexOf(LINE_END, start);
  }
  return end < 0 ? text : text.slice(0, end);
}

/** A change's line that ends with a backslash continuing it (lineContinues), without that backslash and its CR LF's CR. */
const uncontinued = (line) => lineText(line).slice(0, -1);

/**
 * Whether `line`, which names no password itself, ends with a backslash after the start of a password word
 * (cfg['pass\), so that the line after it may finish the name: its reading waits for that line.
 */
function nameGoesOn(line) {
  if (!lineContinues(line)) return false;
  const tail = decodeName(uncontinued(line)).slice(-7).toLowerCase();
  return NAME_STARTS.some((start) => tail.endsWith(start));
}

// ----- What a JavaScript "/", "(" and "{" read as -----------------------------------------------

/**
 * The state of JavaScript code read so far, which decides what a "/" is (a regular expression or a division), what a
 * "(" holds (a statement's condition or not) and what a "{" opens (a block or an object), as JavaScript's grammar does
 * where the tokens before them show it: prev and prev2, the last two tokens that are neither whitespace nor a comment
 * (`before`: the one above the text, or null); nl, whether a line break stands after prev (a block comment that holds
 * one stands for one, as automatic semicolon insertion reads it), and nl2, whether one stands between prev2 and prev,
 * and prop2, whether prev2 is a property's name (each null where that is not read: before the text's own tokens);
 * stack, the groups open
 * (their tokens, each with its kind); known, whether a "/" or a "{" at the text's start is known to start an operand or
 * a statement (the text starts at the file's top, or an expression's); expr, whether the text is an expression (a
 * template's interpolation), so that a "{" at its top opens an object; fns, the function and class keywords whose body
 * has not opened yet (pendingBody), innermost last; at(s), the token of the block the text stands in that starts where
 * the text's character `s` stands (blockTokens), or null where none does or nothing above is known: a "/", a group's
 * opening or close and a "++" or "--" read as the block reads them there; type, the type after as or satisfies being
 * read (typeArguments), or null. `prev2`: the token before `before`, or null.
 */
function codeState(before, { known = false, expr = false, nl = false, at = null, prev2 = null } = {}) {
  return { prev: before, prev2: before ? prev2 : null, nl: Boolean(before) && nl, nl2: null, prop2: null, stack: [], known, expr, at, fns: [], type: null };
}

/**
 * `at` for a text that starts at character `offset` of block reader `b`'s text (codeState), lexed after a lead of
 * `skip` characters: b's token that starts at the same place, or null (the lead's own characters, or a place inside one
 * of b's tokens). The block's lexing read the lines above the text with it, so where the two stand on the same token,
 * the block's reading of it is the one that knows what came before.
 */
function blockTokens(b, offset, skip = 0) {
  return (s) => {
    if (s < skip) return null;
    const q = tokenAt(b, offset + s - skip);
    return q >= 0 ? b.toks[q] : null;
  };
}

/** The block's token where `tok` stands (codeState's `at`), where it is the same token: its kind and its text. */
function sameInBlock(cs, tok) {
  const t = cs.at ? cs.at(tok.s) : null;
  return t && t.k === tok.k && t.v === tok.v && t.e - t.s === tok.e - tok.s ? t : null;
}

/** Whether the last word read is a property's name (a.return, a?.of): an operand, never a keyword. */
const propertyName = (cs) => isOp(cs.prev2, ".") || isOp(cs.prev2, "?.");

/** Whether token `t` is the word `v` as JavaScript spells a keyword: in its own case. */
const isKeyword = (t, v) => Boolean(t) && t.k === "word" && t.v === v;

/**
 * Keywords that end their statement where a line break follows them (JavaScript's restricted productions, read by
 * automatic semicolon insertion): what follows the break starts a statement, so return<line break>{} is a block and
 * return<line break>function f() {} declares f. break and continue end theirs before any "/" or "{" (a label after one
 * on its line too). throw takes no line break after it at all.
 */
const RESTRICTED = new Set(["return", "yield", "break", "continue"]);

/**
 * Words that are keywords in some places only and names elsewhere (of in a for's head, await in an async function or a
 * module, yield in a generator), so a "/" after one may divide (const of = 2; x = of / 2) or open a regular expression,
 * and a line break after one may end the statement or not: the lexer cannot tell which.
 */
const CONTEXTUAL = new Set(["of", "await", "yield"]);

/** Whether the innermost group open in code state `cs` is a "(": a ";" there stands in a for's head. */
const inParens = (cs) => cs.stack.length > 0 && cs.stack[cs.stack.length - 1].v === "(";

/**
 * Whether a statement may start where code state `cs` stands: in a block, or at the top of a text that is no
 * expression's (a template's interpolation holds no statement). Where one ends at a line break (automatic semicolon
 * insertion) anywhere else, JavaScript reads no such code.
 */
const statementAt = (cs) => (cs.stack.length ? cs.stack[cs.stack.length - 1].kind === "block" : !cs.expr);

/**
 * What a "/" reads as after the code read so far: "re" (it opens a regular expression), "div" (it divides) or null,
 * where the tokens before it cannot show which: a ")" or "}" whose group opens above the text, a "}" whose "{" cannot
 * show a block from an object (after ":" in a block, after ">"), a function's or a class's body that may be an
 * expression's or a declaration's (pendingBody: fn null), a word that is a keyword in some places only (CONTEXTUAL),
 * or the text's start where nothing above it is known. A function's or a class's body divides after it where the
 * function or the class is an expression (function () {} / 2), and opens a regular expression where it is a
 * declaration, as a block does. After break or continue, or a label after one on its line, a statement starts. A
 * postfix "++", "--" or TypeScript's non-null "!" (x! / 2) ends an operand.
 */
function slashReading(cs) {
  const p = cs.prev;
  if (!p) return cs.known ? "re" : null;
  if (p.k === "num" || p.k === "str" || p.k === "regex") return "div";
  if (p.k === "word") {
    if (propertyName(cs)) return "div";
    if (p.v === "break" || p.v === "continue") return "re";
    if (cs.nl2 === false && (isKeyword(cs.prev2, "break") || isKeyword(cs.prev2, "continue"))) return "re";
    if (CONTEXTUAL.has(p.v)) return null;
    return REGEX_AFTER.has(p.v) ? "re" : "div";
  }
  if (p.k === "close") {
    if (p.v === "]") return "div";
    if (p.v === ")") return p.kind === "cond" ? "re" : p.kind === "plain" ? "div" : null;
    if (p.fn !== undefined) return p.fn === true ? "div" : p.fn === false ? "re" : null;
    return p.kind === "block" ? "re" : p.kind === "object" ? "div" : null;
  }
  if (isOp(p, "++") || isOp(p, "--") || isOp(p, "!")) return p.post === true ? "div" : p.post === false ? "re" : null;
  // A ">" that closes TypeScript's type arguments after as or satisfies (x as Array<number> / 2) may end an operand.
  if (p.typeClose) return null;
  return "re";
}

/**
 * Read token `tok` into the type after TypeScript's as or satisfies (codeState's type: { d, the depth the word stands at,
 * and angle, the type arguments open there }), before feed moves on: a ">", ">>" or ">>>" there that closes type
 * arguments is marked (typeClose), since a "/" after it divides in TypeScript (x as Array<number> / 2) and opens a
 * regular expression after a comparison's ">" (slashReading: either). A ";", a close that leaves the depth, and outside
 * type arguments a ",", an assignment, "=>", "?" or ":" end the type. as or satisfies after a line break, or after a "."
 * (a property's name), starts none.
 */
function typeArguments(cs, tok) {
  if (tok.k === "word" && (tok.v === "as" || tok.v === "satisfies") && !cs.nl && !isOp(cs.prev, ".") && !isOp(cs.prev, "?.")) {
    cs.type = { d: cs.stack.length, angle: 0 };
    return;
  }
  const t = cs.type;
  if (!t) return;
  if (cs.stack.length < t.d || tok.k === "semi") {
    cs.type = null;
    return;
  }
  if (cs.stack.length !== t.d) return;
  if (isOp(tok, "<")) t.angle++;
  else if (t.angle > 0 && (isOp(tok, ">") || isOp(tok, ">>") || isOp(tok, ">>>"))) {
    t.angle = Math.max(0, t.angle - tok.v.length);
    tok.typeClose = true;
  } else if (t.angle === 0 && (tok.k === "comma" || (tok.k === "op" && (ASSIGN.has(tok.v) || tok.v === "=>" || tok.v === "?" || tok.v === ":")))) cs.type = null;
}

/** TypeScript's words a type follows, so that a "{" after one opens a type literal (x as { a: 1 } / 2), an object to a "/" after it: never a block. */
const TYPE_WORDS = new Set(["as", "satisfies", "is", "keyof", "infer", "readonly", "unique", "asserts", "extends", "implements"]);

/**
 * What a "{" opens after the code read so far: "block", "object" or null (unknown). A line break after a token that
 * ends an operand, or after return or yield, ends the statement there (automatic semicolon insertion), so a "{" after
 * it opens a block; after a word that is a keyword in some places only (CONTEXTUAL) or void (TypeScript's type too) it
 * may open either.
 */
function braceKind(cs) {
  const p = cs.prev;
  if (!p) return cs.expr ? "object" : cs.known ? "block" : null;
  if (p.k === "semi") return inParens(cs) ? "object" : "block";
  if (p.k === "close" || isOp(p, "=>")) return "block";
  if (p.k === "open") return p.v === "{" ? p.kind : "object";
  const ends = cs.nl && statementAt(cs) ? "block" : null;
  if (p.k === "num" || p.k === "str" || p.k === "regex") return ends;
  if (p.k === "word") {
    if (propertyName(cs)) return ends;
    if (BLOCK_WORDS.has(p.v)) return "block";
    if (cs.nl && RESTRICTED.has(p.v)) return ends;
    if (cs.nl && (CONTEXTUAL.has(p.v) || p.v === "void")) return null;
    return REGEX_AFTER.has(p.v) || TYPE_WORDS.has(p.v) ? "object" : "block";
  }
  if (isOp(p, ":")) {
    // A property's value or a ternary's branch inside a call, a list or an object; a case's, a label's or a ternary's in a block.
    const top = cs.stack[cs.stack.length - 1];
    if (!top) return cs.expr ? "object" : null;
    return top.v !== "{" || top.kind === "object" ? "object" : null;
  }
  if (isOp(p, ">")) return null;
  if (isOp(p, "++") || isOp(p, "--") || isOp(p, "!")) return p.post === false ? "object" : p.post === true ? ends : null;
  if (p.k === "op" || p.k === "comma") return "object";
  return null;
}

/** Words before a function's or a class's keyword that make it a declaration (export default function () {}, else function f() {}). */
const DECLARATION_WORDS = new Set(["export", "default", "declare", "abstract", "else", "do"]);

/**
 * Whether the function or class keyword `kind` that follows the code read so far starts an expression (true: a "/"
 * after its body divides it), a declaration (false: a "/" there opens a regular expression, as after a block) or may
 * start either (null). An expression's keyword follows an operator, "(", "[", ",", "=>", a keyword that takes an
 * operand (return, new, typeof, ...) or an interpolation's start; a declaration's follows ";" (outside a for's head), a
 * block's "{", a "}", the file's start, or export, default, declare, abstract, else or do. After a token that ends an
 * operand (a name, a property's name such as obj.return, a literal, a close, a postfix "++" or "!") JavaScript reads a
 * function's keyword only across a line break, which ends the statement there (automatic semicolon insertion): a
 * declaration's, where a statement may start (statementAt; elsewhere JavaScript reads no such code: either); a class's
 * may follow a decorator there (@dec class {}, an expression's or a declaration's): either. A line break after return,
 * yield, break or continue ends the statement too (RESTRICTED); after await, of (CONTEXTUAL), void (TypeScript's type
 * too), throw or a ">" (a type's close) it may. "async" before "function" with no line break between them is passed
 * over (a name otherwise, as before "class"), and the token before it decides (prev2, nl2 and prop2; where those are
 * not read, a line break after a keyword there leaves it either).
 */
function expressionAt(cs, kind) {
  let p = cs.prev;
  let nl = cs.nl;
  let prop = propertyName(cs);
  if (kind === "function" && isKeyword(p, "async") && !prop && !nl) {
    p = cs.prev2;
    nl = cs.nl2;
    prop = cs.prop2;
  }
  if (!p) return cs.expr ? true : cs.known ? false : null;
  const statement = statementAt(cs) ? false : null;
  const after = kind === "function" ? statement : null;
  if (p.k === "semi") return inParens(cs);
  if (p.k === "open") return p.v !== "{" ? true : p.kind === "block" ? false : p.kind === "object" ? true : null;
  if (p.k === "close") {
    if (p.v === ")" && p.kind === "cond") return false;
    return p.v === "}" ? statement : p.v === ")" ? after : kind === "function" ? statement : null;
  }
  if (p.k === "num" || p.k === "str" || p.k === "regex") return after;
  if (p.k === "comma") return true;
  if (p.k === "word") {
    if (prop === true) return after;
    if (DECLARATION_WORDS.has(p.v)) return false;
    const operator = REGEX_AFTER.has(p.v) || p.v === "extends" || p.v === "instanceof";
    if (!operator) return after;
    if (nl === null) return null;
    if (RESTRICTED.has(p.v)) return nl ? statement : true;
    return nl && (prop === null || CONTEXTUAL.has(p.v) || p.v === "void" || p.v === "throw") ? null : true;
  }
  if (p.k !== "op") return null;
  if (isOp(p, "++") || isOp(p, "--") || isOp(p, "!")) return p.post === true ? after : p.post === false ? true : null;
  if (isOp(p, ">")) return nl ? null : true;
  if (isOp(p, ":")) {
    // A property's value or a ternary's branch inside a call, a list or an object; a case's or a label's in a block.
    const top = cs.stack[cs.stack.length - 1];
    if (!top) return cs.expr ? true : null;
    return top.v !== "{" || top.kind === "object" ? true : null;
  }
  return isOp(p, ".") || isOp(p, "?.") ? null : true;
}

/**
 * Whether a "{" after token `p` may open the body of the function or class keyword before it (pendingBody): after its
 * name, its parameters' ")", its return type's last word, ">" or close, or the keyword itself (class {); not after an
 * operator, ",", or a word a type follows (TYPE_WORDS), where it opens a type literal or an object: a return type's
 * { a: string }, <T extends { a: 1 }>, class extends { a: 1 }.b {.
 */
function bodyAfter(p) {
  if (!p) return false;
  if (p.k === "close" || p.k === "str" || p.k === "num") return true;
  if (p.k === "word") return !TYPE_WORDS.has(p.v);
  return isOp(p, ">");
}

/**
 * The function or class keyword at the code state's top whose body has not opened yet, or null: `cs.fns`' last entry,
 * where it was read at the depth the state stands at (the groups open then are open now). An entry: { d, kind
 * ("function" or "class"), expr (expressionAt's), params (a function's "(" has opened: paramsTok), fresh (no token has
 * come after the keyword yet) }. `cs.fns` holds the shallowest first; at one depth, a class's and the function's or
 * class's keywords its extends clause holds (class extends function () {} {}), the class first.
 */
function pendingBody(cs) {
  const fns = cs.fns;
  const top = fns.length ? fns[fns.length - 1] : null;
  return top && top.d === cs.stack.length ? top : null;
}

/**
 * Read token `tok` into code state `cs` (codeState): a group's opening gets its kind (a "(" "cond" after if, while, for,
 * with or for await, else "plain"; a "{" braceKind's; a "[" "plain"), its close the same kind (null where its opening is
 * not in the text), and "++", "--" or "!" whether it is a postfix (post: true after an operand on its line, as
 * TypeScript's non-null x! is, false, or null).
 * A function's or a class's body, the first "{" at its keyword's depth after its parameters (a class's after its
 * keyword) that bodyAfter allows, is a block whose `fn` is expressionAt's at the keyword; a ";", a close of a group around
 * the keyword, or a ":", ",", "=" or "?" right after it (a key or a member named function or class) ends the wait. Where
 * the block the text stands in holds the same token at the same place (codeState's at: sameInBlock), the group's kind,
 * its fn and a "++"'s, a "--"'s or a "!"'s post are the block's, whose lexing read what came before the text.
 */
function feed(cs, tok) {
  if (tok.k === "ws") return;
  if (tok.k === "comment") {
    // A block comment that holds a line break stands for one (return /*<line break>*/ function f() {} declares f).
    if (!tok.line && tok.body.includes(LINE_END)) cs.nl = true;
    return;
  }
  if (tok.k === "nl") {
    cs.nl = true;
    return;
  }
  const pending = pendingBody(cs);
  if (tok.k === "open") {
    if (tok.v === "(") {
      const p = cs.prev;
      const cond = isWord(p) && !propertyName(cs) && (CONDITION_WORDS.has(p.v) || (p.v === "await" && isWord(cs.prev2, "for")));
      tok.kind = cond ? "cond" : "plain";
      if (pending && pending.kind === "function" && !pending.params) {
        pending.params = true;
        pending.paramsTok = tok;
      }
    } else if (tok.v === "{" && pending && (pending.kind === "class" || pending.params) && bodyAfter(cs.prev)) {
      tok.kind = "block";
      tok.fn = pending.expr;
      cs.fns.pop();
    } else tok.kind = tok.v === "{" ? braceKind(cs) : "plain";
    const same = cs.at ? sameInBlock(cs, tok) : null;
    if (same) {
      tok.kind = same.kind;
      if (same.fn !== undefined) tok.fn = same.fn;
      else delete tok.fn;
    }
    cs.stack.push(tok);
  } else if (tok.k === "close") {
    const top = cs.stack[cs.stack.length - 1];
    const matched = Boolean(top) && PAIR[top.v] === tok.v;
    if (matched) {
      cs.stack.pop();
      tok.kind = top.kind;
      if (top.fn !== undefined) tok.fn = top.fn;
    } else {
      const same = cs.at ? sameInBlock(cs, tok) : null;
      tok.kind = same && same.kind !== undefined ? same.kind : null;
      if (same && same.fn !== undefined) tok.fn = same.fn;
    }
    // The keywords read inside the group this close ends, or in the one around the text it leaves, wait no more.
    const depth = cs.stack.length + (matched ? 1 : 0);
    while (cs.fns.length && cs.fns[cs.fns.length - 1].d >= depth) cs.fns.pop();
  } else if (isOp(tok, "++") || isOp(tok, "--") || isOp(tok, "!")) {
    const reading = cs.nl ? "re" : slashReading(cs);
    tok.post = reading === "div" ? true : reading === "re" ? false : null;
    const same = cs.at ? sameInBlock(cs, tok) : null;
    if (same && same.post !== undefined) tok.post = same.post;
  }
  if (tok.k === "word" && (tok.v === "function" || tok.v === "class") && !isOp(cs.prev, ".") && !isOp(cs.prev, "?.")) {
    // A keyword in a class's extends clause waits on top of the class; another function's (an overload's, which has no
    // body) is done waiting.
    if (pending && pending.kind === "function") cs.fns.pop();
    cs.fns.push({ d: cs.stack.length, kind: tok.v, expr: expressionAt(cs, tok.v), params: false, fresh: true });
  } else if (pending && cs.fns[cs.fns.length - 1] === pending) {
    // Still waiting (no body opened, no close ended it): a ";", or a key's or a member's mark right after the keyword, ends it.
    if (tok.k === "semi" || (pending.fresh && (isOp(tok, ":") || tok.k === "comma" || isOp(tok, "=") || isOp(tok, "?")))) cs.fns.pop();
    else pending.fresh = false;
  }
  typeArguments(cs, tok);
  cs.prop2 = cs.prev ? propertyName(cs) : null;
  cs.prev2 = cs.prev;
  cs.nl2 = cs.nl;
  cs.prev = tok;
  cs.nl = false;
}

/**
 * Where a regular expression that opens at `text[i]` ("/") ends, its flags included, or -1 where none closes on its
 * line (then the "/" divides): a line break ends it.
 */
function regexEnd(text, i) {
  const n = text.length;
  let j = i + 1;
  let inClass = false;
  for (; j < n && !breakAt(text, j); j++) {
    if (text[j] === "\\") j++;
    else if (text[j] === "[") inClass = true;
    else if (text[j] === "]") inClass = false;
    else if (text[j] === "/" && !inClass) break;
  }
  if (j >= n || text[j] !== "/") return -1;
  j++;
  while (j < n && /[a-z]/i.test(text[j])) j++;
  return j;
}

/**
 * Whether what follows `text[j]` on its line leaves the lexer where it is, whichever way a "/" there is read: no
 * template, block comment or interpolation opens in it, its brackets balance, and no backslash continues the line.
 * Where it may not, a "/" the lexer cannot read leaves every later line of the text unknown too. The line is the
 * change's (to its LINE_END), whose next line is the next a lead is read for.
 */
function lineLocal(text, j) {
  let e = text.indexOf(LINE_END, j);
  if (e < 0) e = text.length;
  let depth = 0;
  for (let k = j; k < e; k++) {
    const c = text[k];
    if (c === "`" || (c === "/" && text[k + 1] === "*") || (c === "$" && text[k + 1] === "{")) return false;
    if (c === "(" || c === "[" || c === "{") depth++;
    else if (c === ")" || c === "]" || c === "}") depth--;
  }
  return depth === 0 && !lineContinues(text.slice(j, e));
}

// ----- The code lexer ---------------------------------------------------------------------------

/** How a lexer reads a text by default: nothing known above it, no line-start leads recorded. */
const NO_LEX = Object.freeze({});

/**
 * The tokens of `text` as language `L` reads it: ws, nl, comment {body, line, closed}, str {body,
 * reading, fence, closed}, word, num, op, open, close, semi, comma, regex, end (PHP's "?>") and other.
 * `before`: the token above the text, or null. `opts`: { known, nl } (codeState) and record (a JavaScript template
 * keeps, per line it spans, the lead that puts a lexer where it stands at that line's start: scanTemplate).
 * The tokens' `unsure` is where the first "/" stands that JavaScript may read otherwise than the lexer does (a regular
 * expression or a division), or a JavaScript template whose state the lexer cannot know (scanTemplate), or -1; `far`
 * is where the first of them stands whose readings may leave the line differently (lineLocal), or -1: every line
 * after it is unknown.
 */
function lex(text, L, depth, before = null, opts = NO_LEX) {
  // PHP's tokens are PHP's own (phpReader): no text is ever lexed here as PHP.
  if (!WORD[L.id]) throw new Error(`doors.mjs has no lexer for ${L.id}: its tokens are its own tokenizer's`);
  const toks = [];
  toks.unsure = -1;
  toks.far = -1;
  const cs = codeState(before, opts);
  const n = text.length;
  for (let i = 0; i < n; ) {
    const tok = lexOne(text, i, L, depth, cs, opts.record === true);
    if (tok.unsure !== undefined) {
      if (toks.unsure < 0) toks.unsure = tok.unsure;
      if (toks.far < 0 && (tok.k === "str" || !lineLocal(text, tok.unsure))) toks.far = tok.unsure;
    }
    toks.push(tok);
    // Only JavaScript's "/" depends on the tokens before it.
    if (L.regex) feed(cs, tok);
    i = tok.e;
  }
  return toks;
}

/** The token of `text` at `i`, as lex reads it in code state `cs`. */
function lexOne(text, i, L, depth, cs, record) {
  const n = text.length;
  const c = text[i];
  const s = i;
  // A line break (breakAt: CR LF one).
  const terminator = breakAt(text, i);
  if (terminator) return { k: "nl", s, e: i + terminator };
  if (c === " " || c === "\t" || c === "\f" || c === "\v") {
    while (i < n && " \t\f\v".includes(text[i])) i++;
    return { k: "ws", s, e: i };
  }
  // Python's explicit line joining: a backslash and the line break after it.
  const joined = L.lineJoin && c === "\\" ? breakAt(text, i + 1) : 0;
  if (joined) return { k: "ws", s, e: i + 1 + joined };
  // A line comment's text ends at the line's end, a block comment's at "*/".
  if ((L.slash && c === "/" && text[i + 1] === "/") || (L.hash && c === "#")) {
    let e = nextBreak(text, i);
    if (e < 0) e = n;
    return { k: "comment", s, e, body: text.slice(i + (c === "#" ? 1 : 2), e), line: true, closed: true };
  }
  if (L.block && c === "/" && text[i + 1] === "*") {
    const close = text.indexOf("*/", i + 2);
    const e = close < 0 ? n : close + 2;
    return { k: "comment", s, e, body: text.slice(i + 2, close < 0 ? n : close), line: false, closed: close >= 0 };
  }
  // Strings. A single quote between two letters is prose (don't), unless a Python prefix stands before it.
  const prefix = L.prefixes && /[rRbBuUfF]/.test(c) && !/[\w$]/.test(text[i - 1] ?? "") ? (sticky(PY_PREFIX, text, i) ?? "") : "";
  const q = text[i + prefix.length];
  const reading = L.quotes[q];
  if (reading && !(q === "'" && !prefix && /[A-Za-z]/.test(text[i - 1] ?? "") && /[A-Za-z]/.test(text[i + 1] ?? ""))) {
    // A JavaScript template, its interpolations read as code.
    if (reading === "template") return scanTemplate(text, i, L, depth, record);
    const start = i + prefix.length;
    const close = L.triple && text.startsWith(q.repeat(3), start) ? q.repeat(3) : q;
    const read = /f/i.test(prefix) ? "fstring" : reading;
    const body = start + close.length;
    let j = body;
    let closed = false;
    for (; j < n; j++) {
      // A backslash escapes the character after it, or continues the string over the line break after it (CR LF one).
      if (text[j] === "\\") j += breakAt(text, j + 1) === 2 ? 2 : 1;
      else if (text.startsWith(close, j)) {
        closed = true;
        break;
      } else if (close.length === 1 && breakAt(text, j)) break;
    }
    j = Math.min(j, n);
    const e = closed ? j + close.length : j;
    return { k: "str", s, e, body: text.slice(body, j), reading: read, fence: q, depth, closed, triple: close.length > 1 };
  }
  if (q === "'" && !prefix && reading) return { k: "other", s, e: i + 1, v: c };
  // A JavaScript regular expression literal, where no operand stands before the "/" (slashReading). Where the tokens
  // before it cannot show which it is and one closes on its line, the token is marked: the lexer's state is unsure.
  // Where the block the text stands in holds a "/" at the same place (codeState's at), the block's reading is the one
  // that read what came before, and its doubt is the text's too.
  if (L.regex && c === "/") {
    const known = cs.at ? cs.at(s) : null;
    const block = known && (known.k === "regex" || (known.k === "op" && known.v[0] === "/")) ? known : null;
    let readAs = block ? (block.unsure !== undefined ? null : block.k === "regex" ? "re" : "div") : slashReading(cs);
    const e = readAs === "div" ? -1 : regexEnd(text, i);
    let unsure = false;
    if (e >= 0 && readAs === null) {
      unsure = true;
      readAs = block ? (block.k === "regex" ? "re" : "div") : cs.prev ? "div" : "re";
    }
    if (e >= 0 && readAs === "re") return unsure ? { k: "regex", s, e, unsure: s } : { k: "regex", s, e };
    if (unsure) return { k: "op", s, e: i + (text[i + 1] === "=" ? 2 : 1), v: text[i + 1] === "=" ? "/=" : "/", unsure: s };
  }
  const word = sticky(WORD[L.id], text, i);
  if (word) return { k: "word", s, e: i + word.length, v: wordName(word, L) };
  const number = c === "." || (c >= "0" && c <= "9") ? sticky(NUMBER, text, i) : null;
  if (number) return { k: "num", s, e: i + number.length, v: number };
  const kind = "([{".includes(c) ? "open" : ")]}".includes(c) ? "close" : c === ";" ? "semi" : c === "," ? "comma" : null;
  if (kind) return { k: kind, s, e: i + 1, v: c };
  const op = OPERATORS.find((o) => text.startsWith(o, i));
  if (op) return { k: "op", s, e: i + op.length, v: op };
  return { k: "other", s, e: i + 1, v: c };
}

/**
 * How many groups a template's interpolation may hold open at a line's start, with the function and class keywords
 * waiting there for their body (class extends<line break>class extends ...), before the lexer's state there counts as
 * unknown (scanTemplate): a line's lead holds each of them, so the leads stay linear. Past it the line is outside the
 * plain subset.
 */
const LEAD_FRAMES = 64;

/** A template's text, in scanTemplate's frames. */
const TEMPLATE_TEXT = Object.freeze({ k: "`" });
/** An interpolation's code, in scanTemplate's frames: its own code state. */
const interpolation = () => ({ k: "${", cs: codeState(null, { known: true, expr: true }) });

/**
 * A JavaScript template that opens at `text[s]` ("`"), read as JavaScript reads it: its text to the "`" that closes it,
 * and each interpolation as code (lexOne: strings, groups, operators, names) to the "}" that closes it. A str token,
 * `unsure` where its state stops being known (templateEnd), the rest of the text then its own. With `record`, `leads`
 * maps each line start inside it to the lead that puts a lexer there (templateLead), or to null where more than
 * LEAD_FRAMES groups and keywords waiting for their body stand open there.
 */
function scanTemplate(text, s, L, depth, record) {
  const end = templateEnd(text, s + 1, L, depth, [TEMPLATE_TEXT], record);
  const tok = { k: "str", s, e: end.e, body: text.slice(s + 1, end.closed ? end.e - 1 : end.e), reading: "template", fence: "`", depth, closed: end.closed, triple: false };
  if (end.unsure >= 0) tok.unsure = end.unsure;
  if (end.leads) tok.leads = end.leads;
  return tok;
}

/**
 * Read `text` from `i` inside a template's `frames` ([TEMPLATE_TEXT], or [interpolation()] for an interpolation on its
 * own) to where the outermost closes: { e (after it, or the text's end), closed, unsure (or -1), leads (with `record`) }.
 * The plain subset's interpolation holds strings, groups, operators and names. A template, a comment or a regular
 * expression in one is outside it, and so is a "/" the lexer cannot read there, a quote a line break ends and a close
 * that matches no opening: there the template's state stops being known (unsure), and the rest of the text is the
 * template's. `open` counts what a line's lead would hold: the frames, and in the interpolation its groups and the
 * function and class keywords waiting for their body (pendingBody).
 */
function templateEnd(text, i, L, depth, frames, record) {
  const n = text.length;
  const leads = record ? new Map() : null;
  let open = frames.length;
  const unknown = (at) => ({ e: n, closed: false, unsure: at, leads });
  const note = (at, leaf) => {
    if (leads) leads.set(at, open > LEAD_FRAMES ? null : templateLead(frames, leaf));
  };
  while (i < n) {
    const top = frames[frames.length - 1];
    const c = text[i];
    if (top.k === "`") {
      // A template's text holds line breaks (CR LF one); each one's end is a line's start.
      if (c === "\\") {
        const escaped = breakAt(text, i + 1);
        if (escaped) note(i + 1 + escaped, "");
        i += 1 + (escaped || 1);
      } else if (c === "`") {
        return { e: i + 1, closed: true, unsure: -1, leads };
      } else if (c === "$" && text[i + 1] === "{") {
        frames.push(interpolation());
        open++;
        i += 2;
      } else {
        const terminator = breakAt(text, i);
        if (terminator) note(i + terminator, "");
        i += terminator || 1;
      }
      continue;
    }
    const { cs } = top;
    if (c === "`") return unknown(i);
    if (c === "}" && !cs.stack.length) {
      frames.pop();
      open -= 1 + cs.fns.length;
      i++;
      if (!frames.length) return { e: i, closed: true, unsure: -1, leads };
      continue;
    }
    const tok = lexOne(text, i, L, depth, cs, false);
    if (tok.unsure !== undefined) return unknown(tok.unsure);
    if (tok.k === "comment" || tok.k === "regex" || (tok.k === "str" && !tok.closed && tok.e < n)) return unknown(tok.s);
    if (tok.k === "nl") note(tok.e, "");
    else if (tok.k === "str") {
      // A string continued by a backslash over lines: its line starts are inside it.
      for (let k = nextBreak(text, tok.s); k >= 0 && k < tok.e; k = nextBreak(text, k + 1)) {
        const terminator = breakAt(text, k);
        if (k + terminator <= tok.e) note(k + terminator, tok.fence);
        k += terminator - 1;
      }
    }
    const before = cs.stack.length + cs.fns.length;
    feed(cs, tok);
    if (tok.k === "close" && tok.kind === null) return unknown(tok.s);
    open += cs.stack.length + cs.fns.length - before;
    i = tok.e;
  }
  return { e: n, closed: false, unsure: -1, leads };
}

/**
 * What makes a function's or a class's keyword an expression's (true), a declaration's (false) or either (null) in a
 * lead (expressionAt), wherever the lead stands and without ending the wait of a keyword before it: "=", "do" or "of"
 * and a line break (a word that is a keyword in some places only).
 */
const fnLead = (expr) => (expr === true ? "=" : expr === false ? "do " : `of${LINE_END}`);

/**
 * A group open in an interpolation, as text that opens one of its kind (feed) wherever the lead stands (a ";" in a "("
 * is a for's head's): "if(" a condition's, "=>{" a block, "={" an object, ">{" unknown, and a function's body as a
 * function whose keyword reads as the body's did (fnLead).
 */
function groupLead(g) {
  if (g.v === "(") return g.kind === "cond" ? "if(" : "(";
  if (g.v === "[") return "[";
  if (g.fn !== undefined) return `${fnLead(g.fn)}function(){`;
  return g.kind === "block" ? "=>{" : g.kind === "object" ? "={" : ">{";
}

/**
 * Text that, with a line break after it, leaves code state `cs` reading a "/", a "(", a "{", a "++", a "!", a function's
 * or a class's keyword and a property's name after it as its last tokens do (slashReading, feed, braceKind,
 * expressionAt).
 */
function prevLead(cs) {
  const p = cs.prev;
  if (!p || p.k === "open") return "";
  if (p.k === "word") {
    if (propertyName(cs)) return "_._";
    if (p.v === "await" && isWord(cs.prev2, "for")) return "for await";
    // A label after break or continue on its line: a statement starts after it.
    if (cs.nl2 === false && (isKeyword(cs.prev2, "break") || isKeyword(cs.prev2, "continue"))) return `${cs.prev2.v} _`;
    const keyword = REGEX_AFTER.has(p.v) || RESTRICTED.has(p.v) || CONDITION_WORDS.has(p.v) || BLOCK_WORDS.has(p.v) || TYPE_WORDS.has(p.v) || DECLARATION_WORDS.has(p.v);
    return keyword || p.v === "instanceof" ? p.v : "_";
  }
  if (p.k === "num" || p.k === "str" || p.k === "regex") return "0";
  if (p.k === "close") {
    if (p.v === "]") return "[]";
    if (p.v === ")") return p.kind === "cond" ? "if()" : "()";
    // A function's or a class's body: a "/" after it reads as after a block (a declaration's), an object (an expression's) or neither.
    const reading = p.fn !== undefined ? (p.fn === true ? "object" : p.fn === false ? "block" : null) : p.kind;
    return reading === "block" ? "=>{}" : reading === "object" ? "={}" : ">{}";
  }
  if (isOp(p, "++") || isOp(p, "--") || isOp(p, "!")) return p.post === true ? `0${p.v}` : p.post === false ? p.v : `>{}${p.v}`;
  if (isOp(p, "/") || isOp(p, "/=")) return `0${p.v}`;
  // A type's close after as or satisfies: a "/" after it reads either way, as after of (CONTEXTUAL).
  if (p.typeClose) return "of";
  if (p.k === "op") return p.v;
  if (p.k === "comma") return ",";
  if (p.k === "semi") return ";";
  return ">";
}

/**
 * A function or class keyword whose body has not opened yet (pendingBody), as text that leaves a lexer waiting the same
 * way: the keyword read as an expression's, a declaration's or either (fnLead), a name where a token has come after it,
 * and "()" where its parameters have closed; `group`, the group open at its depth, is its parameters where they are open.
 */
function pendingLead(p, group) {
  const params = p.params && group !== p.paramsTok ? "() " : "";
  return `${fnLead(p.expr)}${p.kind} ${p.fresh ? "" : "_ "}${params}`;
}

/**
 * An interpolation's code state `cs` as a lead (templateLead): its groups (groupLead), each after the function or class
 * keyword that waits at its depth (pendingLead), and a "{" at such a keyword's depth, which is not its body (that ends
 * the wait), as a type literal's ("<{").
 */
function interpolationLead(cs) {
  let lead = "${";
  let f = 0;
  for (let k = 0; k <= cs.stack.length; k++) {
    const g = k < cs.stack.length ? cs.stack[k] : null;
    let waits = false;
    for (; f < cs.fns.length && cs.fns[f].d === k; f++) {
      lead += pendingLead(cs.fns[f], g);
      waits = true;
    }
    if (g) lead += waits && g.v === "{" ? "<{" : groupLead(g);
  }
  return lead;
}

/**
 * The lead that puts a lexer where a template's `frames` stand at a line's start (leadOf): the template's "`", its
 * interpolation's "${", the groups open in it and the function or class keywords waiting for their body
 * (interpolationLead), then, in the interpolation's code, its last tokens (prevLead; none right after a waiting
 * keyword, which pendingLead has put there) and the line break before the line, and `leaf`: a quote a backslash
 * continues (' or ") that the line starts inside.
 */
function templateLead(frames, leaf) {
  let lead = "";
  for (const f of frames) lead += f.k === "`" ? "`" : interpolationLead(f.cs);
  const top = frames[frames.length - 1];
  if (top.k === "${") {
    const p = pendingBody(top.cs);
    lead += `${p && p.fresh ? "" : prevLead(top.cs)}${LINE_END}`;
  }
  return lead + leaf;
}

/**
 * Where the interpolation whose "${" stands at `text[i]` ends, after its "}", read as scanTemplate reads one, or -1
 * where it does not close in the text or its state is unsure there.
 */
function interpolationEnd(text, i) {
  const end = templateEnd(text, i + 2, LANGUAGES.js, 0, [interpolation()], false);
  return end.closed && end.unsure < 0 ? end.e : -1;
}

/**
 * The tokens of `text`, which starts inside a string or a block comment that a line above opens, lexed after
 * `lead` (leadOf: the text that puts the lexer where the lines above leave it) and `prev` (the last token before the
 * string or the comment that is neither whitespace nor a comment, or null), in `text`'s own positions. The first token
 * holds the lead: it starts at 0, and is marked `lead` (it started on a line above).
 */
function lexAfter(lead, text, L, depth, prev, opts = NO_LEX) {
  const toks = lex(lead + text, L, depth, prev, opts);
  const n = lead.length;
  for (const t of toks) {
    t.s = Math.max(0, t.s - n);
    t.e = Math.max(0, t.e - n);
  }
  if (toks.unsure >= 0) toks.unsure = Math.max(0, toks.unsure - n);
  if (toks.far >= 0) toks.far = Math.max(0, toks.far - n);
  if (toks.length) toks[0].lead = true;
  return toks;
}

// ----- Reading tokens ---------------------------------------------------------------------------

const TRIVIA = new Set(["ws", "comment"]);
const PAIR = { "(": ")", "[": "]", "{": "}" };

/**
 * A reader over one text: its tokens, match[i] (the close of the group token i opens, -1 when it does
 * not close in the text), parent[i] (the group that holds token i), and `end`: what the text's end
 * means. "line": the next line is not known; "more": ask for it; "change": the change ends here, and
 * what follows is not the change's; "comment": a comment's text, whose end ends its values. `before`:
 * what the lines above the text tell of it (contextAt: the group it starts inside, outerOf, and the block it is part
 * of, which decorator reads back into), or null where nothing is known above it. `lead`: where the text starts
 * inside a string or a comment a line above opens, the text that puts the lexer there (leadOf), or ""; `prev`, the
 * last token above the text, or before that string or comment, that is neither whitespace nor a comment (lex,
 * lexAfter); `opts`, lex's.
 */
function reader(text, L, depth, end, before = null, lead = "", prev = null, opts = NO_LEX) {
  return readerOver(text, lead ? lexAfter(lead, text, L, depth, prev, opts) : lex(text, L, depth, prev, opts), L, depth, end, before);
}

/** A reader (reader's) over `text` and its tokens `toks`, already lexed: a lexer's, or PHP's own (phpReader). */
function readerOver(text, toks, L, depth, end, before = null) {
  const match = new Int32Array(toks.length).fill(-1);
  const opener = new Int32Array(toks.length).fill(-1);
  const parent = new Int32Array(toks.length).fill(-1);
  const stack = [];
  for (let i = 0; i < toks.length; i++) {
    parent[i] = stack.length ? stack[stack.length - 1] : -1;
    const t = toks[i];
    if (t.k === "open") stack.push(i);
    else if (t.k === "close" && stack.length && PAIR[toks[stack[stack.length - 1]].v] === t.v) {
      const open = stack.pop();
      match[open] = i;
      opener[i] = open;
      parent[i] = stack.length ? stack[stack.length - 1] : -1;
    }
  }
  // The text's first line is the change's line it starts with (LINE_END): a name must stand on it.
  const nl = text.indexOf(LINE_END);
  return { text, L, depth, end, toks, match, opener, parent, before, outer: null, firstEnd: nl < 0 ? text.length : nl, memo: new Map(), groups: new Map(), items: new Map(), types: new Map() };
}

/**
 * The group the text starts inside, read from the lines above it (reader's `before`): { context, setter }.
 * context: its declaration's (groupContext), so a field at the text's top level is a schema's or a label's
 * only where that declaration says so (a schema map whose "{" opens on a line above). setter: the group is the
 * arguments of a call handed a name and its value (a set call opened on a line above). Read only when asked.
 */
function outerOf(r) {
  r.outer ??= r.before ? r.before.outer(r.L) : NO_OUTER;
  return r.outer;
}

/**
 * What the entries above `lines[index]` tell a reader of a text that starts on its line (reader's `before`):
 * outer(L), the group the line starts inside (outerAt); lead(L), the text that puts the lexer where the lines
 * above leave it at the line's start (leadOf: "" outside any string or comment, null where that is unknown), and
 * prev(L), the last token above the line, or before that string or comment, that is neither whitespace nor a comment
 * (lex, lexAfter); and
 * above(L), the block it is part of in language `L` (layoutOf): { b, offset, top, span } (a reader over the block's
 * text, where the line starts in it, whether the block starts at the file's first line, so that nothing stands
 * above it, and the block's token the line starts inside, or -1); and the entries and the line's index in them, where
 * a PHP text's tokens are taken from its block's (phpReader).
 */
function contextAt(lines, index) {
  return {
    lines,
    index,
    outer: (L) => outerAt(lines, index, L),
    lead(L) {
      const layout = layoutOf(lines, index, L);
      return layout.leads[index - layout.start];
    },
    prev(L) {
      const layout = layoutOf(lines, index, L);
      return layout.prevs[index - layout.start];
    },
    prev2(L) {
      const layout = layoutOf(lines, index, L);
      return layout.prev2s[index - layout.start];
    },
    above(L) {
      const layout = layoutOf(lines, index, L);
      const k = index - layout.start;
      return { b: layout.b, offset: layout.offset[k], top: startsText(lines[layout.start]), span: layout.spans[k] };
    },
  };
}

/** The token of reader `r` that starts at character `s` of its text, by a binary search, or -1 where none does (s stands inside one). */
function tokenAt(r, s) {
  const { toks } = r;
  let lo = 0;
  let hi = toks.length;
  while (lo < hi) {
    const mid = (lo + hi) >> 1;
    if (toks[mid].s < s) lo = mid + 1;
    else hi = mid;
  }
  return lo < toks.length && toks[lo].s === s ? lo : -1;
}

/** Per markerHits' entries: each entry's block (the entries around it whose line numbers follow on), and what is read of it once. */
const ENTRIES = new WeakMap();

/**
 * The read-once state of markerHits' entries: top[k], the first entry of entry k's block; mentions, the entries whose
 * line names a password (the lines a statement over lines may start on), in order; and per language each block's
 * layout (layoutOf), each mention's statement as far as it is known open or ended (openThrough), the links past
 * the mentions whose statement ended (endedLinks), the characters the one reading has read back (spend), and each
 * entry's verdict (verdictsOf).
 */
function entriesOf(lines) {
  let state = ENTRIES.get(lines);
  if (state) return state;
  const top = new Int32Array(lines.length);
  for (let k = 0; k < lines.length; k++) {
    top[k] = k > 0 && typeof lines[k].text === "string" && typeof lines[k - 1].text === "string" && followsOn(lines, k - 1) ? top[k - 1] : k;
  }
  state = { top, mentions: mentionsOf(lines), layouts: new Map(), statements: new Map(), links: new Map(), spent: new Map(), verdicts: new Map(), unplain: new Map(), crossing: new Map(), comments: new Map(), named: new Map(), yaml: null, php: null, scripts: null };
  ENTRIES.set(lines, state);
  return state;
}

/** Whether entry `k` is the line before the next entry, and a backslash at its end continues it there. */
const continuesOn = (lines, k) => typeof lines[k].text === "string" && lineContinues(lines[k].text) && followsOn(lines, k);

/**
 * The entries whose line names a password (mentions), in order: in its own text, or with the lines a backslash at its
 * end continues (a name a string spells over lines: 'pass\<line break>word'). Read from the last entry up, so each
 * line is decoded once at most: `head` holds the first characters of what the next entry's line goes on with, decoded.
 */
function mentionsOf(lines) {
  const named = [];
  let head = "";
  for (let k = lines.length - 1; k >= 0; k--) {
    const { text } = lines[k];
    if (typeof text !== "string") {
      head = "";
      continue;
    }
    const on = continuesOn(lines, k);
    let decoded = null;
    let own = mentions(text);
    if (!own && on && head) {
      decoded = decodeName(uncontinued(text));
      own = mentions(decoded.slice(-7) + head);
    }
    if (own) named.push(k);
    if (k > 0 && continuesOn(lines, k - 1)) {
      decoded ??= decodeName(on ? uncontinued(text) : text);
      head = (decoded + (on ? head : "")).slice(0, 7);
    } else head = "";
  }
  return named.reverse();
}

/**
 * Whether reading back `chars` more characters keeps the entries' reading `language` within READ_BACK_CHARS, counted.
 * Only verdictsOf's one reading per language spends, so the count is that reading's.
 */
function spend(lines, language, chars) {
  const { spent } = entriesOf(lines);
  const total = (spent.get(language) ?? 0) + chars;
  spent.set(language, total);
  return total <= READ_BACK_CHARS;
}

/** The map `state[field]` keeps for `key` (a language), made on first use. */
function perLanguage(state, field, key) {
  let map = state[field].get(key);
  if (!map) state[field].set(key, (map = new Map()));
  return map;
}

/**
 * The text that, put before a line that starts at character `at` of block reader `b`'s text inside its token `t` (a
 * string or a block comment that a line above opens), puts the lexer where the block's lexing stands there, so the
 * line read on its own lexes as the block does: a block comment's "/" + "*" (the comment holds a line break, which
 * ends a statement as one does: aboveBreak), a quoted
 * string's prefix and quotes ('...\ continued, Python's r""", and where a language has triple quotes a single one
 * with an escaped line break after it), and in a JavaScript template the lead the block's lexing recorded for that
 * line (scanTemplate: the template's "`", its interpolation's "${" and the groups open in it, its last tokens, and a
 * quote the line starts inside). null where that state is unknown: the template's state stopped being known before
 * the line (templateEnd), or more than LEAD_FRAMES groups and keywords waiting for their body stand open there.
 */
function leadOf(b, t, at) {
  const tok = b.toks[t];
  const { text } = b;
  // A regular expression the lexer reads over an escaped line break (no language lets one) leaves the line in code.
  if (tok.k !== "str" && tok.k !== "comment") return "";
  if (tok.k === "comment") return "/*";
  if (tok.reading === "template") return tok.leads?.get(at) ?? null;
  const quote = text.indexOf(tok.fence, tok.s);
  const opening = text.slice(tok.s, quote + (tok.triple ? 3 : 1));
  // A quote continued by a backslash where a language has triple quotes: an escaped line break after it, so that a
  // line that starts with two quotes ('a\ then '') does not read as a triple quote's opening.
  return b.L.triple && !tok.triple ? `${opening}\\${LINE_END}` : opening;
}

/**
 * Whether a line break stands between `prev`, the token above a line, and the line's own tokens, for a line read after
 * `lead` (leadOf): outside any string or comment, and inside a block comment a line above opens, which holds one
 * (codeState's nl). A string's lead is a token that comes first.
 */
const aboveBreak = (lead, prev) => prev !== null && (!lead || lead === "/*");

/**
 * The block of entries around `lines[index]`, read once per language `L`: { start, end (its first and last entries),
 * b (a reader over its text, its lines joined), offset[k - start] (where entry k's line starts in that text), outers[k -
 * start] (outerAt), leads[k - start] (leadOf, or "" where the line starts outside any string or comment), prevs[k -
 * start] (the block's last token above the line that is neither whitespace nor a comment: lex's and lexAfter's prev),
 * spans[k - start] (the token of b the line starts inside, or -1), lead, unplain (the first entry where the lexer's
 * reading leaves the plain subset: unplainOf, or -1) }. A block that
 * starts below the file's first line may start inside a block comment: one that a "*" + "/" closes before any opens
 * is read as that comment's end (lead: the "/" + "*" put before the text, or 0); its lines read on their own as
 * before, since that comment is a guess.
 */
function layoutOf(lines, index, L) {
  const state = entriesOf(lines);
  const layouts = perLanguage(state, "layouts", L.id);
  const start = state.top[index];
  const known = layouts.get(start);
  if (known) return known;
  let end = start;
  while (followsOn(lines, end)) end++;
  const text = lines.slice(start, end + 1).map((entry) => entry.text).join(LINE_END);
  // A PHP block's tokens are PHP's own (phpBlock): its lexical state is never guessed.
  const php = L.id === "php" ? phpBlock(text, lines[start].line) : null;
  const closes = L.block && !startsText(lines[start]) ? text.indexOf("*/") : -1;
  const opens = text.indexOf("/*");
  const lead = closes >= 0 && (opens < 0 || closes < opens) ? "/*" : "";
  // What stands above the block is known only at the file's top. The block's templates record each line's lead.
  const b = php ? readerOver(text, php.toks, L, 0, "more") : reader(lead + text, L, 0, "more", null, "", null, { known: startsText(lines[start]), record: true });
  if (php) b.html = php.html ?? null;
  const offset = new Array(end - start + 1);
  const outers = new Array(end - start + 1);
  const leads = new Array(end - start + 1).fill("");
  const prevs = new Array(end - start + 1).fill(null);
  const prev2s = new Array(end - start + 1).fill(null);
  const spans = new Int32Array(end - start + 1).fill(-1);
  const stack = [];
  const { far } = b.toks;
  let unknown = false;
  // The first entry outside the plain subset by the lexer's reading (unplainOf), or -1.
  let unplain = -1;
  let last = null;
  let last2 = null;
  let t = 0;
  let at = lead.length;
  for (let k = start; k <= end; k++) {
    if (k > start) at += lines[k - 1].text.length + 1;
    offset[k - start] = at;
    for (; t < b.toks.length && b.toks[t].e <= at; t++) {
      const tok = b.toks[t];
      if (!TRIVIA.has(tok.k) && tok.k !== "nl") {
        last2 = last;
        last = tok;
      }
      if (tok.k === "open") stack.push(t);
      else if (tok.k === "close" && stack.length && PAIR[b.toks[stack[stack.length - 1]].v] === tok.v) stack.pop();
    }
    // A line that starts inside a string or a comment a line above opens reads on its own after its lead (leadOf), and
    // what follows the string or the comment on it stands in the group that holds them. The comment a block is read as
    // starting inside is a guess (lead): its lines read on their own as before. Where the lexer's state is unknown at
    // one line's start, it is at every later line's too: the lexer read what came after it otherwise than the
    // language does, or may have, and so it is after a "/" above that JavaScript may read otherwise than the lexer
    // does where its two readings may leave its line differently (lex: far). Every line reads on from the block's last
    // token above it (prev), so a "/", a "(" or a "{" at its start reads as the block's does.
    const inside = t < b.toks.length && b.toks[t].s < at;
    prevs[k - start] = last;
    prev2s[k - start] = last2;
    if (unknown || (far >= 0 && far < at)) {
      leads[k - start] = null;
      unknown = true;
    } else if (inside && !(lead && b.toks[t].s === 0)) {
      spans[k - start] = t;
      leads[k - start] = leadOf(b, t, at);
      unknown = leads[k - start] === null;
    }
    // The plain subset ends at the first line whose lexer state is unknown, or the line that holds the "/" or the
    // template that makes it so (far), and in PHP the line where its block's reading stops being known (phpBlock).
    const doubt = php && !php.failure && php.unknown >= 0 && php.unknown <= at + lines[k].text.length;
    if (unplain < 0 && (leads[k - start] === null || (far >= at && far <= at + lines[k].text.length) || doubt)) unplain = k;
    const open = stack[stack.length - 1];
    const placed = leads[k - start] === null ? false : !inside || leads[k - start] !== "";
    outers[k - start] = k === start || !placed || open === undefined ? NO_OUTER : { context: groupContext(b, open), setter: setterCall(b, open), bracket: b.toks[open].v };
  }
  const layout = { start, end, b, offset, outers, leads, prevs, prev2s, spans, lead: lead.length, unplain, php };
  layouts.set(start, layout);
  return layout;
}

/**
 * The group that `lines[index]`'s line starts inside, in language `L`: read from the lines above it in the file
 * that the entries hold (added or unchanged), all of them back to where the entries stop following on (layoutOf).
 * A line that starts inside a string or a comment stands in the group around it, where the lexer's state there is known
 * (leadOf); the block's first line, a line whose state is unknown, and a line inside no group give NO_OUTER. The
 * group is { context (groupContext), setter (setterCall), bracket: the "(", "[" or "{" that opens it }.
 */
function outerAt(lines, index, L) {
  const layout = layoutOf(lines, index, L);
  return layout.outers[index - layout.start];
}

/** The first token from `i` that is not whitespace or a comment, nor a newline when `lines`. */
function skip(r, i, lines = false) {
  const { toks } = r;
  while (i < toks.length && (TRIVIA.has(toks[i].k) || (lines && toks[i].k === "nl"))) i++;
  return i;
}

/** The token before `i` that is not whitespace or a comment, nor a newline when `lines`; -1 at the start. */
function back(r, i, lines = true) {
  const { toks } = r;
  i--;
  while (i >= 0 && (TRIVIA.has(toks[i].k) || (lines && toks[i].k === "nl"))) i--;
  return i;
}

const isOp = (t, v) => Boolean(t) && t.k === "op" && (v === undefined || t.v === v);
const isWord = (t, v) => Boolean(t) && t.k === "word" && (v === undefined || t.v.toLowerCase() === v);
const isGroupEnd = (t) => t.k === "semi" || t.k === "comma" || t.k === "close" || t.k === "end";

/** Whether a line that starts with token `k` continues the value before it: an operator, a joining word, "(", "[" or a template. */
function continues(r, k) {
  const t = r.toks[k];
  if (t.k === "op") return !(r.L.id === "js" && (t.v === "++" || t.v === "--"));
  if (t.k === "word") return JOINING.has(t.v.toLowerCase()) && !STARTING.has(t.v.toLowerCase());
  if (t.k === "open") return t.v !== "{";
  return t.k === "str" && t.fence === "`" && r.L.id === "js";
}

const NO_CONTEXT = Object.freeze({ schema: false, label: false });
/** No group around the text: no declaration, no call, and no bracket that opens it. */
const NO_OUTER = Object.freeze({ context: NO_CONTEXT, setter: false, bracket: null });

/**
 * A group's own declaration: { schema, label, bound, start }. bound: a call's arguments or a body, whose fields
 * have no declaration of their own and take none from the groups around them. start: where the group's
 * declaration starts (PHP's array before its "(").
 */
function ownDeclaration(r, open) {
  const { toks } = r;
  const t = toks[open];
  const before = back(r, open);
  const b = toks[before];
  let start = open;
  if (t.v === "(") {
    if (isWord(b, "array")) start = before;
    // A call's arguments, a function's parameters, or a group after a keyword (if, return, ...).
    else if (b && (b.k === "word" || b.k === "close" || b.k === "str")) return { schema: false, label: false, bound: true, start };
  } else if (t.v === "{" && b && (b.k === "word" || b.k === "close" || isOp(b, "=>"))) {
    // A body: a function's, a class's or a block's statements.
    return { schema: false, label: false, bound: true, start };
  }
  let name = "";
  const op = back(r, start);
  // A subscript's group is declared by what it subscripts ($labels['password'] = ...).
  if (t.v === "[" && toks[op]?.k === "word") name = toks[op].v;
  else if (op >= 0 && ["=", "=>", ":", ":=", "??="].some((v) => isOp(toks[op], v))) {
    let k = back(r, op);
    if (toks[k]?.k === "close" && toks[k].v === "]") k = back(r, k);
    const n = toks[k];
    if (n && (n.k === "word" || n.k === "str")) name = n.k === "word" ? n.v : n.body;
  }
  return { schema: SCHEMA_DECLARATION.test(name), label: LABEL_DECLARATION.test(name), bound: false, start };
}

/**
 * Whether the fields of the group that opens at token `open` are a schema or labels: by the name its
 * declaration gives it ($expectedColumns = [...], const labels = {...}, 'rules' => [...]), or a group
 * around it, up to the nearest call's arguments or body: a group handed to a call, or a statement in a
 * body, has no declaration, wherever the call stands. Read at most READ_DEPTH groups up, iteratively.
 */
function groupContext(r, open) {
  if (open < 0) return NO_CONTEXT;
  const chain = [];
  let context = null;
  let g = open;
  while (g >= 0 && chain.length <= READ_DEPTH) {
    if (r.groups.has(g)) {
      context = r.groups.get(g);
      break;
    }
    const own = ownDeclaration(r, g);
    chain.push({ g, own });
    if (own.bound) break;
    g = r.parent[own.start];
  }
  // At the text's top, the group the text starts inside declares what is left (outerOf).
  context ??= g < 0 ? outerOf(r).context : NO_CONTEXT;
  for (let k = chain.length - 1; k >= 0; k--) {
    const { g, own } = chain[k];
    context = own.bound ? NO_CONTEXT : { schema: context.schema || own.schema, label: context.label || own.label };
    r.groups.set(g, context);
  }
  return context;
}

/**
 * Read the value that starts at token `i`: { lit, end, open }. lit: it holds a literal, or what the
 * reader cannot read; end: the token that ends it; open: it ran to the text's end (EMPTY, OPEN or
 * UNENDED). `ctx`: flag (the name is a flag's), group (an item of a group, where a newline is a space),
 * field (the group it is a field of), reading (the quotes its strings are read by). A chain of
 * assignments is followed without recursion, and its links are kept.
 */
function readValue(r, i, depth, ctx) {
  const { toks, match } = r;
  const memoKey = (at) =>
    ctx.group ? null : `${at}:${ctx.flag ? 1 : 0}:${ctx.tuple ? 1 : 0}:${ctx.structure ? 1 : 0}:${ctx.key ? 1 : 0}:${ctx.helper ? 1 : 0}:${ctx.field ?? ""}:${ctx.reading ?? ""}`;
  const key = memoKey(i);
  if (key && r.memo.has(key)) return r.memo.get(key);
  // A test of equality is a boolean, whatever its operands hold ("password" === g.type, $v !== '').
  const equality = ctx.names ? -1 : equalityEnd(r, i, ctx);
  if (equality >= 0) {
    const value = { lit: false, end: equality, open: null };
    if (key) r.memo.set(key, value);
    return value;
  }
  let expect = true;
  let operands = 0;
  // A string with literal characters, while it is the value's only operand: a placeholder, or a schema or label its field declares.
  let lone = null;
  // The value's only operand is a function: anything after it but the value's end counts.
  let fn = false;
  let j = i;
  const result = (value) => {
    if (key) r.memo.set(key, value);
    return value;
  };
  const done = (end, open = null) => {
    // A lone string that is no placeholder counts whatever follows; one that is may still be joined on the next line.
    if (lone && !exemptString(r, lone, ctx)) return result({ lit: true, end });
    return result({ lit: false, end, open });
  };
  const literal = (end) => result({ lit: true, end });
  for (;;) {
    while (j < toks.length && TRIVIA.has(toks[j].k)) {
      if (toks[j].k === "comment" && !toks[j].closed) return result({ lit: false, end: toks.length, open: OPEN });
      j++;
    }
    if (j >= toks.length) return done(j, expect ? (operands ? OPEN : EMPTY) : UNENDED);
    const t = toks[j];
    if (t.k === "nl") {
      if (expect || ctx.group) {
        j++;
        continue;
      }
      const k = skip(r, j, true);
      if (k >= toks.length) {
        if (toks.slice(j).some((x) => x.k === "comment" && !x.closed)) return result({ lit: false, end: toks.length, open: OPEN });
        return done(k, UNENDED);
      }
      if (!continues(r, k)) return done(j);
      j = k;
    }
    const u = toks[j];
    if (expect && r.L.id === "js" && isOp(u, "<")) {
      // TypeScript's generic arrow function: <T>(x: T): R => ...; its type parameters are no value. One the text
      // ends inside is open.
      const a = angleEnd(r, j);
      const params = a < 0 ? a : skip(r, a, true);
      const arrow = params >= toks.length ? UNFINISHED : params >= 0 ? arrowParams(r, params) : false;
      if (a === UNFINISHED || arrow === UNFINISHED) return result({ lit: false, end: toks.length, open: OPEN });
      if (arrow === AFTER_RETURN_TYPE) return done(toks.length, UNENDED);
      if (arrow) {
        j = params;
        continue;
      }
    }
    if (!expect && r.L.id === "js" && isOp(u, ":") && toks[back(r, j)]?.v === ")") {
      // An arrow function's return type ((): Map<string, string> => ...) is no value: read on from its "=>". One the
      // text ends inside is open, and one the reader cannot read counts.
      const arrow = arrowParams(r, r.opener[back(r, j)]);
      if (arrow === UNFINISHED) return result({ lit: false, end: toks.length, open: OPEN });
      if (arrow === AFTER_RETURN_TYPE) return done(toks.length, UNENDED);
      if (arrow === -1) return literal(toks.length);
      if (arrow) {
        j = skip(r, tsType(r, j + 1), true);
        continue;
      }
    }
    if (expect) {
      // An ellipsis alone (Python's Ellipsis, or prose's "...") and a placeholder in angle brackets are no value.
      const after = isOp(u, "...") ? skip(r, j + 1) : -1;
      if (after >= 0 && (after >= toks.length || isGroupEnd(toks[after]) || toks[after].k === "nl")) {
        operands++;
        j = after;
        expect = false;
        continue;
      }
      const angle = isOp(u, "<") ? /^<[\w .-]{1,200}>/.exec(r.text.slice(u.s, u.s + 210)) : null;
      if (angle) {
        operands++;
        while (j < toks.length && toks[j].s < u.s + angle[0].length) j++;
        expect = false;
        continue;
      }
      // PHP's "?:" is one operator between two operands, as "??" is.
      if ((u.k === "op" && PREFIX.has(u.v)) || (u.k === "word" && JOINING.has(u.v.toLowerCase())) || (r.L.id === "php" && isOp(u, ":") && isOp(toks[back(r, j)], "?"))) {
        j++;
        continue;
      }
      if (r.L.id === "php" && u.k === "open" && u.v === "(") {
        const inner = skip(r, j + 1);
        const close = skip(r, inner + 1);
        if (isWord(toks[inner]) && PHP_CASTS.has(toks[inner].v.toLowerCase()) && toks[close]?.k === "close" && toks[close].v === ")") {
          j = close + 1;
          continue;
        }
      }
      if (isGroupEnd(u)) return literal(j);
      const o = readOperand(r, j, depth, ctx, operands === 0);
      if (o.lit) return literal(o.end);
      if (o.open) return result({ lit: false, end: o.end, open: OPEN });
      operands++;
      if (o.text !== undefined) {
        // Joined to another operand, a string's literal characters count.
        if (operands > 1) return literal(j);
        lone = o.text;
      }
      if (o.fn) {
        // A function is all of what a name is given, or it counts: grouped, handed to a call, invoked, joined or
        // read further, the value is what its body returns, which is not read. As all of a helper's value
        // (validatePassword: function (v) {...}) it is no value; as all of another name's, its value is what
        // its body returns (bodyValue).
        if (ctx.group || ctx.names || depth > 0 || operands > 1) return literal(o.end);
        if (!ctx.helper) {
          const body = bodyValue(r, o.body, depth, ctx.reading);
          if (body.lit) return literal(o.end);
          if (body.open) return result({ lit: false, end: toks.length, open: OPEN });
        }
        fn = true;
      }
      j = o.end;
      expect = false;
      continue;
    }
    if (u.k === "comma" && ctx.tuple && !ctx.group) {
      if (lone) return literal(j);
      j++;
      expect = true;
      continue;
    }
    if (isGroupEnd(u)) return done(j);
    if (fn) return literal(j);
    if (u.k === "op" && ASSIGN.has(u.v)) {
      // A chain: the value is what its last name is given. Read on from there, or take it when it was read.
      const link = memoKey(j + 1);
      if (link && r.memo.has(link)) return result(r.memo.get(link));
      j++;
      expect = true;
      operands = 0;
      lone = null;
      continue;
    }
    if (lone) {
      // A template that is wholly placeholders, formatted with its values: '%s' % pw, '{}'.format(pw).
      const format = isOp(u, "%") || (isOp(u, ".") && isWord(toks[skip(r, j + 1)], "format"));
      if (!format || !bareTemplate(lone)) return literal(j);
      lone = null;
    }
    if (u.k === "op") {
      if (u.v === "=>") {
        // An arrow function's expression is its value. Its block body is no value of a helper's, and of any other
        // name's it is what the body returns; grouped, handed on or read further, the arrow counts (readItems).
        if (ctx.names) return literal(j);
        const k = skip(r, j + 1, true);
        if (toks[k]?.k === "open" && toks[k].v === "{") {
          if (ctx.helper || ctx.group || depth > 0) return done(k);
          const body = bodyValue(r, k, depth, ctx.reading);
          if (body.lit) return literal(k);
          if (body.open) return result({ lit: false, end: toks.length, open: OPEN });
          j = match[k] + 1;
          expect = false;
          fn = true;
          continue;
        }
      }
      j++;
      expect = true;
      continue;
    }
    if (u.k === "word" && JOINING.has(u.v.toLowerCase())) {
      j++;
      expect = true;
      continue;
    }
    // A word beside a complete operand: a shell's or prose's next word, where the value ended.
    if (u.k === "word") return done(j);
    return literal(j);
  }
}

/** Equality operators: their result is a boolean. */
const EQUALITY = new Set(["==", "===", "!=", "!==", "<>"]);
const EQUALITY_WORDS = new Set(["instanceof", "in", "is", "not"]);
/** What binds tighter than an equality in PHP, JavaScript and Python: what may stand beside one at a value's top. */
const TIGHTER = new Set([".", "->", "?->", "::", "?.", "+", "-", "*", "/", "%", "**", "//", "<<", ">>", ">>>", "!", "~", "@", "++", "--"]);
const TIGHTER_WORDS = new Set(["new", "typeof", "await", "clone", "void", "delete"]);

/**
 * The token that ends the value from token `i` when its top level is a test of equality (==, ===, !=, <>,
 * instanceof, in, is, not in) beside only what binds tighter than one: the value is a boolean. -1 otherwise,
 * and where the value's end is not in the text. Groups are passed over whole, once.
 */
function equalityEnd(r, i, ctx) {
  const { toks, match } = r;
  let equal = false;
  let expect = true;
  for (let j = i; j < toks.length; j++) {
    const t = toks[j];
    if (TRIVIA.has(t.k)) {
      if (t.k === "comment" && !t.closed) return -1;
      continue;
    }
    if (t.k === "nl") {
      if (expect || ctx.group) continue;
      const k = skip(r, j, true);
      if (k >= toks.length) return -1;
      if (!continues(r, k)) return equal ? j : -1;
      j = k - 1;
      continue;
    }
    if (isGroupEnd(t)) return equal && !(t.k === "comma" && ctx.tuple) ? j : -1;
    if (t.k === "op") {
      if (EQUALITY.has(t.v)) equal = true;
      else if (!TIGHTER.has(t.v)) return -1;
      expect = true;
      continue;
    }
    if (t.k === "word" && JOINING.has(t.v.toLowerCase())) {
      const w = t.v.toLowerCase();
      if (EQUALITY_WORDS.has(w)) equal = true;
      else if (!TIGHTER_WORDS.has(w)) return -1;
      expect = true;
      continue;
    }
    if (t.k === "open") {
      if (match[j] < 0) return -1;
      j = match[j];
    } else if ((t.k === "str" && !t.closed) || t.k === "other") return -1;
    expect = false;
  }
  return -1;
}

/** Whether a string that is a whole value is no password: blank, a placeholder or an example, or a schema or label its field's group declares. */
function exemptString(r, text, ctx) {
  const value = text.trim();
  if (!value || isPlaceholder(value)) return true;
  // The declaration is read only for a value spelled as a schema type or a label.
  const schema = SCHEMA_VALUE.test(value);
  const label = LABEL_VALUE.test(value);
  if (!schema && !label) return false;
  let field;
  if (ctx.field !== undefined && ctx.field >= 0) field = groupContext(r, ctx.field);
  else if (ctx.key && ctx.field === -1) field = outerOf(r).context;
  else return false;
  return (field.schema && schema) || (field.label && label);
}

/**
 * The roles of the call whose callee is the word at token `j` (READERS): by the file's language and the
 * call's form, a plain call, a method on an instance or a PHP static, or UNKNOWN_CALL. In PHP "." joins
 * strings: a page's script is never PHP's code (phpBlock's js reads it as JavaScript).
 */
function callOf(r, j) {
  const { toks, L } = r;
  const name = toks[j].v;
  const p = back(r, j);
  const via = toks[p];
  const receiver = p >= 0 ? toks[back(r, p)] : undefined;
  const T = READERS[L.id];
  const known = (roles, self = "") => ({ roles: self + roles, keywords: Object.hasOwn(T.keywords, name) ? T.keywords[name] : {}, known: true });
  if (L.id === "php" && isOp(via, "::")) {
    const key = isWord(receiver) ? receiver.v.replace(/^\\/, "") + "::" + name : null;
    return key !== null && Object.hasOwn(T.statics, key) ? known(T.statics[key]) : UNKNOWN_CALL;
  }
  const member = L.id === "php" ? isOp(via, "->") || isOp(via, "?->") : isOp(via, ".") || isOp(via, "?.");
  if (member) {
    // PHP's "->" is read on an object, never a module: its readers are lookups.
    if (L.id === "php") return Object.hasOwn(T.lookups, name) ? known(T.lookups[name]) : UNKNOWN_CALL;
    const chain = receiverOf(r, p);
    const path = chain.path === null ? null : `${chain.path}.${name}`;
    if (path !== null && Object.hasOwn(T.members, path)) return known(T.members[path]);
    // A built-in type's method, read through the type: unbound, so its first argument is self, a value.
    if (L.id === "py" && chain.path !== null && PY_TYPES.has(chain.path)) return Object.hasOwn(T.typed, name) ? known(T.typed[name], "v") : UNKNOWN_CALL;
    // A class's, a type's or a namespace's method is a function of its arguments, not an instance's lookup.
    if (isWord(receiver) && (CLASS_NAME.test(receiver.v) || JS_NAMESPACES.has(receiver.v))) return UNKNOWN_CALL;
    if (Object.hasOwn(T.lookups, name)) return known(T.lookups[name]);
    // A transform only on a receiver the table understands: a chain from the runtime's own objects or the
    // environment, or what a member it knows returns (crypto.randomBytes(16).toString('hex')).
    const rooted = chain.prefix !== null && T.roots.some((root) => chain.prefix === root || chain.prefix.startsWith(`${root}.`));
    const understood = rooted || (chain.call >= 0 && Object.hasOwn(T.members, memberPath(r, chain.call) ?? ""));
    if (Object.hasOwn(T.transforms, name) && understood) return known(T.transforms[name]);
    return UNKNOWN_CALL;
  }
  // A plain call: PHP's global function by its name (\getenv too), never a variable's ($fn) or a namespaced one.
  if (L.id === "php" && (name.startsWith("$") || name.slice(1).includes("\\"))) return UNKNOWN_CALL;
  const plain = L.id === "php" ? name.replace(/^\\/, "") : name;
  return Object.hasOwn(T.calls, plain) ? known(T.calls[plain]) : UNKNOWN_CALL;
}

/**
 * The receiver of the method whose "." or "?." is token `p`, read back over its chain: { path, prefix, call }.
 * path: the chain's names joined by "." when it is names only (os.environ), else null. prefix: its names from
 * its start up to its first call or subscript (window.location.pathname; document.querySelector in
 * document.querySelector('#x').value; os.environ in os.environ['X']), or null when it starts with anything
 * but a name (a string, a group, a list). call: the callee of the call that is the chain's last link
 * (randomBytes in crypto.randomBytes(16)), or -1. At most 64 links.
 */
function receiverOf(r, p) {
  const { toks, opener } = r;
  const none = { path: null, prefix: null, call: -1 };
  // The chain's links from its end back: a name, or a call's or a subscript's group (null).
  const links = [];
  let call = -1;
  let k = back(r, p);
  while (k >= 0 && links.length < 64) {
    const t = toks[k];
    if (t.k === "close" && t.v !== "}") {
      // A call's arguments or a subscript: the chain goes on with what stands before it.
      const open = opener[k];
      const before = open < 0 ? -1 : back(r, open);
      if (before < 0 || !(toks[before].k === "word" || (toks[before].k === "close" && toks[before].v !== "}"))) return none;
      if (!links.length && t.v === ")" && toks[before].k === "word") call = before;
      links.push(null);
      k = before;
      continue;
    }
    if (t.k !== "word") return none;
    links.push(t.v);
    const d = back(r, k);
    if (d >= 0 && (isOp(toks[d], ".") || isOp(toks[d], "?."))) {
      k = back(r, d);
      continue;
    }
    links.reverse();
    const group = links.indexOf(null);
    return { path: group < 0 ? links.join(".") : null, prefix: (group < 0 ? links : links.slice(0, group)).join("."), call };
  }
  return none;
}

/** The whole path of names of the method call whose callee is the word at token `j` (crypto.randomBytes), or null. */
function memberPath(r, j) {
  const p = back(r, j);
  if (!(isOp(r.toks[p], ".") || isOp(r.toks[p], "?."))) return null;
  const chain = receiverOf(r, p);
  return chain.path === null ? null : `${chain.path}.${r.toks[j].v}`;
}

/**
 * One operand from token `j`, with what follows it (properties are operands of "." and "->"; calls,
 * subscripts and a TypeScript "!" here): { lit, end, open, text (a string with literal characters,
 * which may still be exempt as the whole value), fn and body (a function, and the token its body opens
 * at) }. `lead`: it is the value's first operand, so a map it opens may be the value's own structure
 * (readItems). `ctx.names`: the operand names what a reader reads, or is an option: a string's
 * characters and a number are no literal there, and a map, a list or a function is.
 */
function readOperand(r, j, depth, ctx, lead = false) {
  const { toks, match } = r;
  const t = toks[j];
  let end = j + 1;
  let callee = null;
  let text;
  if (t.k === "word") {
    const lower = t.v.toLowerCase();
    // A function value: `function` in PHP and JavaScript, and PHP's arrow fn (...) =>. Elsewhere, and
    // fn(...) that no arrow follows, the word is a name, and what follows it is read as a call.
    if (lower === "function" && r.L.id !== "py") return readFunction(r, j, lower, depth, ctx);
    // PHP's static closure and arrow are the function after the word.
    if (lower === "static" && r.L.id === "php") {
      const k = skip(r, j + 1, true);
      if (isWord(toks[k], "function") || isWord(toks[k], "fn")) return readOperand(r, k, depth, ctx, lead);
    }
    if (lower === "fn" && r.L.id === "php") {
      const arrow = readFunction(r, j, lower, depth, ctx);
      if (arrow.lit || arrow.open || isOp(toks[arrow.end], "=>")) return arrow;
    }
    if (lower === "array" && r.L.id === "php" && toks[skip(r, j + 1)]?.v === "(") {
      if (ctx.names) return { lit: true, end };
      const open = skip(r, j + 1);
      const items = readItems(r, open, depth, ctx, null, lead && ownStructure(r, open, ctx));
      if (items.lit || items.open) return items;
      end = items.end;
    } else callee = j;
  } else if (t.k === "num") {
    // !0 and !1 are minified booleans; 0 and 1 are a flag's switch.
    if (!ctx.names && !(isOp(toks[back(r, j)], "!") && /^[01]$/.test(t.v)) && !(ctx.flag && /^[01]$/.test(t.v))) return { lit: true, end };
  } else if (t.k === "str") {
    if (!t.closed) return { lit: false, end: toks.length, open: true };
    const value = partsValue(partsOf(t, ctx.reading ?? t.reading));
    // A value an escape spells is never a placeholder, a blank or a label: its characters count.
    if (value.bad || (!ctx.names && value.text && (value.refs || value.esc))) return { lit: true, end };
    if (value.text && !ctx.names) text = value.text;
  } else if (t.k === "open") {
    if (ctx.names && t.v !== "(") return { lit: true, end: match[j] < 0 ? toks.length : match[j] + 1 };
    const items = readItems(r, j, depth, ctx, null, lead && t.v !== "(" && ownStructure(r, j, ctx));
    if (items.lit || items.open) return items;
    end = items.end;
  } else if (!(t.k === "regex" && ctx.names)) {
    return { lit: true, end };
  }
  for (;;) {
    const k = skip(r, end);
    const f = toks[k];
    if (!f) break;
    const attached = k === end;
    if (f.k === "open" && f.v === "(" && t.k !== "num") {
      // A string's literal characters flow into what is called on it.
      if (text) return { lit: true, end: k };
      const items = readItems(r, k, depth, ctx, callee === null ? UNKNOWN_CALL : callOf(r, callee));
      if (items.lit || items.open) return items;
      end = items.end;
      callee = null;
      continue;
    }
    if (f.k === "open" && f.v === "[" && attached) {
      if (text) return { lit: true, end: k };
      if (match[k] < 0) return { lit: false, end: toks.length, open: true };
      end = match[k] + 1;
      callee = null;
      continue;
    }
    if (isOp(f, "!") && attached && !isOp(toks[k + 1], "=")) {
      end = k + 1;
      continue;
    }
    // A tagged template's literal characters are the call's.
    if (f.k === "str" && f.fence === "`" && r.L.id === "js" && attached) return { lit: true, end: k + 1 };
    break;
  }
  return { lit: false, end, text };
}

/**
 * A function value: its body is no value here (an arrow's expression, after "=>", is read as one). Its
 * parameters' defaults are read as values (paramsHoldLiteral), unless it is a helper's (ctx.helper).
 */
function readFunction(r, j, keyword, depth, ctx) {
  const { toks, match } = r;
  let k = skip(r, j + 1, true);
  if (isOp(toks[k], "*") && r.L.id === "js") k = skip(r, k + 1, true);
  if (isWord(toks[k])) k = skip(r, k + 1, true);
  // TypeScript's generic parameters: function <T>(x: T) {...}. Where the text ends before the parameters, or inside
  // them, the function is open.
  if (r.L.id === "js" && isOp(toks[k], "<")) {
    const a = angleEnd(r, k);
    if (a === UNFINISHED) return { lit: false, end: toks.length, open: true };
    if (a < 0) return { lit: true, end: k };
    k = skip(r, a, true);
  }
  if (k >= toks.length) return { lit: false, end: toks.length, open: true };
  if (toks[k].k !== "open" || toks[k].v !== "(") return { lit: true, end: k };
  if (match[k] < 0) return { lit: false, end: toks.length, open: true };
  if (!ctx.helper && paramsHoldLiteral(r, k, depth, ctx.reading)) return { lit: true, end: match[k] + 1 };
  k = skip(r, match[k] + 1, true);
  if (isWord(toks[k], "use") && toks[skip(r, k + 1, true)]?.v === "(") {
    const u = skip(r, k + 1, true);
    if (match[u] < 0) return { lit: false, end: toks.length, open: true };
    k = skip(r, match[u] + 1, true);
  }
  if (isOp(toks[k], ":") && r.L.id === "js") {
    // TypeScript's whole return type; one the reader cannot read counts, and one the text ends inside is open.
    const e = tsType(r, k + 1);
    if (e === UNFINISHED) return { lit: false, end: toks.length, open: true };
    if (e < 0) return { lit: true, end: k };
    k = skip(r, e, true);
  } else if (isOp(toks[k], ":")) {
    k = skip(r, k + 1, true);
    while (isWord(toks[k]) || isOp(toks[k], "?") || isOp(toks[k], "|")) k = skip(r, k + 1, true);
  }
  if (keyword === "fn") return { lit: false, end: k };
  if (toks[k]?.k === "open" && toks[k].v === "{") return { lit: false, end: match[k] < 0 ? toks.length : match[k] + 1, fn: true, body: k };
  // A body the next line may open.
  if (k >= toks.length) return { lit: false, end: toks.length, open: true };
  return { lit: true, end: k };
}

/**
 * Whether the parameter list that opens at token `open` holds a literal: each default, given by "=" wherever it
 * stands in the list (value = 'x', $value = 'x', value: str = 'x', a pattern's { v = 'x' } = {}), is read as a
 * value, and counts unless it is all read, holds no literal and ends at its parameter's end. A function returns
 * its defaults when it is called without them, and a body is not read for where its parameters go. `reading`: the
 * function's string reading, or undefined for each string's own.
 */
function paramsHoldLiteral(r, open, depth, reading) {
  const { toks, match } = r;
  const close = match[open];
  if (close < 0) return true;
  if (depth >= READ_DEPTH) return true;
  for (let k = open + 1; k < close; k++) {
    if (!isOp(toks[k], "=")) continue;
    const s = skip(r, k + 1, true);
    if (s >= close) return true;
    const value = readValue(r, s, depth + 1, { group: true, reading });
    if (value.lit || value.open || value.end > close || !(toks[value.end].k === "comma" || toks[value.end].k === "close")) return true;
    k = value.end;
  }
  return false;
}

/**
 * What the function body that opens at token `open` gives: { lit } when it returns or assigns a literal, or what
 * it does not read whole (bodyStatements), { open } when the body does not close in the text, or {}. Its strings are
 * read by `reading` (paramsHoldLiteral).
 */
function bodyValue(r, open, depth, reading) {
  const close = r.match[open];
  if (close < 0) return { open: true };
  return bodyStatements(r, open + 1, close, open, depth, reading);
}

/**
 * The statements of a body among tokens [from, to), whose own group is `base` (a body's "{", or for Python the
 * group the def stands in): { lit } when a return or a yield, at any depth (a nested function's too), or a
 * statement's assignment holds a literal or is not read whole, or {}. What a body returns is its value, and what
 * it assigns flows into it (const x = 'a'; return x;); a call's keyword argument or a loop's header is no
 * statement. A body that returns and assigns nothing returns no literal. Its strings are read by `reading`
 * (paramsHoldLiteral).
 */
function bodyStatements(r, from, to, base, depth, reading) {
  const { toks, parent } = r;
  if (depth >= READ_DEPTH) return { lit: true };
  // A statement's own: only blocks stand between it and the body.
  const statement = (k) => {
    let g = parent[k];
    if (r.L.id === "py") return g === base;
    while (g !== base) {
      if (g < 0 || toks[g].v !== "{") return false;
      g = parent[g];
    }
    return true;
  };
  for (let k = from; k < to; k++) {
    let s;
    if (isWord(toks[k], "return") || isWord(toks[k], "yield")) {
      // A line break after return ends the statement in JavaScript (and Python), not in PHP.
      s = skip(r, k + 1, r.L.id === "php");
      if (r.L.id === "py" && isWord(toks[k], "yield") && isWord(toks[s], "from")) s = skip(r, s + 1);
    } else if (toks[k].k === "op" && ASSIGN.has(toks[k].v) && statement(k)) {
      s = skip(r, k + 1, true);
    } else continue;
    const t = toks[s];
    if (!t || s >= to || t.k === "nl" || t.k === "semi" || t.k === "close" || t.k === "end") continue;
    const value = readValue(r, s, depth + 1, { reading });
    // A statement that runs to the text's end has ended where the change or a comment ends there, and in Python
    // where no bracket is open around it (verdictAtEnd): a line's end ends a statement there.
    const ended = value.open === UNENDED && (r.end === "change" || r.end === "comment" || (!r.L.statements && !openAround(r, s)));
    if (value.lit || (value.open && !ended) || value.end > to) return { lit: true };
  }
  return {};
}

/**
 * The items of the group that opens at token `open`: a group, list, map or call's arguments. An item
 * may have a key (before "=>" or ":", or a call's keyword "="), and a spread. `call`: the callee's roles
 * and whether READERS knows it (callOf), or null for a group. `structure`: the group is a map or list that is
 * all of a name's value, or of a structure's item (ownStructure). { lit, end, open }. A name's or an
 * option's argument is read as a name (ctx.names), and so is all of it, a call inside it included.
 *
 * A key is no value only where a reader shows what it is: a structure's keys name its fields, a known
 * reader's keywords name its parameters, and a plain group's are a parameter's type. Anywhere else a
 * literal key counts, because what the map is handed to may return it (Object.keys({...}),
 * array_key_first([...]), ''.join({...})), and so does a keyword argument's name in a call the reader
 * does not know (dict(name=None), a PHP named argument).
 */
function readItems(r, open, depth, ctx, call, structure = false) {
  const { toks, match } = r;
  const close = match[open];
  if (close < 0) return { lit: false, end: toks.length, open: true };
  if (depth >= READ_DEPTH) return { lit: true, end: close + 1 };
  // A list's or map's items are its fields; a plain group's are the value's own.
  const plain = !call && toks[open].v === "(" && !isWord(toks[back(r, open)], "array");
  const field = call ? undefined : plain ? ctx.field : open;
  const names = Boolean(ctx.names);
  const keysCount = !names && (call ? !call.known : !plain && !structure);
  // A structure's items are the structure too: a map or list that is all of one is read as its own.
  const item = { flag: ctx.flag, group: true, field, reading: ctx.reading, structure: !call && !plain && structure, names };
  let index = 0;
  // A spread (...args, *args, **kwargs) hands the call arguments whose places are unknown: it and every later one are values.
  let spread = false;
  for (let j = skip(r, open + 1, true); j < close; j = skip(r, j, true)) {
    let keyword = null;
    const a = toks[j];
    const after = skip(r, j + 1, true);
    if ((a.k === "word" || a.k === "str" || a.k === "num") && (isOp(toks[after], "=>") || isOp(toks[after], ":") || (call && a.k === "word" && isOp(toks[after], "=")))) {
      if (keysCount && literalKey(r, a, toks[after].v, call !== null, ctx.reading)) return { lit: true, end: close + 1 };
      keyword = a.k === "word" ? a.v : "";
      j = after + 1;
    } else if (keysCount && !call && r.L.id === "js" && toks[open].v === "{" && a.k === "word" && (after === close || toks[after].k === "comma")) {
      // A JavaScript shorthand property ({ name }) is keyed by its name, as { name: name } is.
      return { lit: true, end: close + 1 };
    } else if (a.k === "open" && a.v === "[" && match[j] > 0 && isOp(toks[skip(r, match[j] + 1, true)], ":")) {
      // A computed key ([expr]: value) is read as a value where a literal key counts.
      if (keysCount) {
        const key = readValue(r, skip(r, j + 1, true), depth + 1, { group: true, reading: ctx.reading });
        if (key.lit || key.open || key.end !== match[j]) return { lit: true, end: close + 1 };
      }
      j = skip(r, match[j] + 1, true) + 1;
    }
    if (call && keyword === null && (isOp(toks[j], "...") || isOp(toks[j], "*") || isOp(toks[j], "**"))) spread = true;
    const role = !call || spread ? "v" : keyword === null ? roleAt(call.roles, index) : Object.hasOwn(call.keywords, keyword) ? call.keywords[keyword] : "v";
    const itemEnd = nextItem(r, j, close);
    if (names || role === "n" || role === "o") {
      // What a reader reads, or an option: a string, a number or a reference, never a map, a list or a function.
      const value = readValue(r, j, depth + 1, names ? item : { ...item, names: true });
      if (value.lit || value.open || value.end !== itemEnd) return { lit: true, end: close + 1 };
    } else if (role === "f") {
      // A template: a string that is wholly placeholders or references, or a value.
      const s = toks[j];
      const lone = s.k === "str" && s.closed && skip(r, j + 1, true) === itemEnd ? partsValue(partsOf(s, ctx.reading ?? s.reading)) : null;
      if (lone && !lone.bad && lone.text && (lone.esc || !bareTemplate(lone.text))) return { lit: true, end: close + 1 };
      if (!lone) {
        const value = readValue(r, j, depth + 1, item);
        if (value.lit || value.open || value.end !== itemEnd) return { lit: true, end: close + 1 };
      }
    } else {
      const value = readValue(r, j, depth + 1, item);
      if (value.lit || value.open || value.end !== itemEnd) return { lit: true, end: close + 1 };
    }
    index++;
    if (itemEnd >= close) break;
    j = itemEnd + 1;
  }
  return { lit: false, end: close + 1 };
}

/**
 * Whether an item's key holds a literal: a number, a string with literal characters, a JavaScript
 * property name, and in a call a Python keyword argument's or a PHP named argument's name. A PHP
 * constant, a Python variable and a JavaScript arrow's parameter are references.
 */
function literalKey(r, a, op, call, reading) {
  if (a.k === "num") return true;
  if (a.k === "str") {
    if (!a.closed) return true;
    const value = partsValue(partsOf(a, reading ?? a.reading));
    return value.bad || value.text.length > 0;
  }
  if (call) return (r.L.id === "py" && op === "=") || (r.L.id === "php" && op === ":" && !a.v.startsWith("$"));
  return r.L.id === "js" && op === ":";
}

/**
 * Whether the map or list that opens at `open` is the value's own structure: the value is one (all of a
 * name's value, or a structure's item) and nothing follows the group but the value's end.
 */
function ownStructure(r, open, ctx) {
  if (!ctx.structure || r.match[open] < 0) return false;
  const k = skip(r, r.match[open] + 1, Boolean(ctx.group));
  const t = r.toks[k];
  if (!t) return true;
  if (t.k === "nl") {
    const m = skip(r, k, true);
    return m >= r.toks.length || !continues(r, m);
  }
  return isGroupEnd(t);
}

function roleAt(roles, index) {
  if (index < roles.length && roles[index] !== "+") return roles[index];
  return roles.endsWith("+") ? roles[roles.length - 2] : "v";
}

/** The comma that ends the item at `j` in the group that closes at `close`, or `close`. */
function nextItem(r, j, close) {
  const { toks, match } = r;
  for (let k = j; k < close; k++) {
    if (toks[k].k === "open") {
      if (match[k] < 0 || match[k] > close) return close;
      k = match[k];
    } else if (toks[k].k === "comma") return k;
  }
  return close;
}

// ----- Targets: where a password name is given a value ------------------------------------------

/**
 * Whether token `i` stands where a key does: at the text's or a line's start, after "{", "[", "(" or ",", after a
 * statement's ";" or a block's "}" (the next member of a class or a statement on one line: x() {} password() {...},
 * x = 1; password: string = ...), a list dash or doc comment's "*", or a declaration's word.
 */
function keyPosition(r, i) {
  const p = back(r, i, false);
  if (p < 0) return true;
  const t = r.toks[p];
  if (t.k === "nl" || t.k === "open" || t.k === "comma" || t.k === "semi" || (t.k === "close" && t.v === "}")) return true;
  if (isOp(t, "*") || isOp(t, "-") || isOp(t, "**")) {
    const q = back(r, p, false);
    return q < 0 || r.toks[q].k === "nl";
  }
  // TypeScript: a member's modifier (override password: ...), or a decorator before it (@Input() password: ...).
  if (r.L.id === "js" && memberModified(r, p)) return true;
  return t.k === "word" && DECLARING.has(t.v);
}

/** Whether token `p`, before a name, is a TypeScript member's modifier (override, abstract, accessor) or ends a decorator. */
function memberModified(r, p) {
  const t = r.toks[p];
  return isWord(t, "override") || isWord(t, "abstract") || isWord(t, "accessor") || (p >= 0 && decorator(r, p));
}

/** How many tokens a decorator's type arguments are read back over before they are taken for a decorator's (decorator). */
const DECORATOR_TOKENS = 2000;

/** What decoratorWalk gives where its text does not hold the decorator's start: it reached the text's start, or a group or a type argument list that opens above it. */
const AT_START = "start";
const OPENS_ABOVE = "above";

/** JavaScript's reserved words: none names a decorator, so a walk that stops on one at a text's start found none. */
const JS_RESERVED = new Set([
  "break", "case", "catch", "class", "const", "continue", "debugger", "default", "delete", "do", "else", "enum", "export",
  "extends", "false", "finally", "for", "function", "if", "import", "in", "instanceof", "new", "null", "return", "super",
  "switch", "this", "throw", "true", "try", "typeof", "var", "void", "while", "with", "yield", "let", "static", "await",
  "implements", "interface", "package", "private", "protected", "public",
]);

/**
 * Whether token `p`, before a name, ends a JavaScript decorator: "@" and its whole expression, read back from its
 * end (decoratorWalk). Where the text does not hold the decorator's start (a line read alone whose "@" or callee
 * stands on a line above: @\nDec() password, @Dec\n() password, or arguments that open above it), the walk is read
 * again from the same token in the block of entries the text is part of (contextAt), which holds every line above it
 * that the entries do. Where that block does not hold it either, nothing stands above a block that starts at the
 * file's first line, so a walk that reached its start found no "@", and a group or a list that opens above it
 * counts as a decorator's (as one in a text with no lines known above it does); above any other block the
 * decorator may start, so the name is taken for a decorated member's (fail closed), unless the walk stopped on a
 * reserved word, which no decorator is named by.
 */
function decorator(r, p) {
  const read = decoratorWalk(r, p);
  if (typeof read === "boolean") return read;
  const above = r.before?.above(r.L);
  if (!above) return read === OPENS_ABOVE;
  const { b, offset, top, span } = above;
  // A first token that holds its text's lead (a string a line above opens: @Dec(`x\ny`) password) is the block's token
  // the line starts inside. Elsewhere none starting there means the text starts inside a string or a comment the
  // block's lexing reads otherwise: what it reads as a name is none.
  const q = r.toks[p].lead ? span : tokenAt(b, offset + r.toks[p].s);
  if (q < 0) return false;
  const again = decoratorWalk(b, q);
  if (typeof again === "boolean") return again;
  if (top) return again === OPENS_ABOVE;
  const first = b.toks[skip(b, 0, true)];
  return again === OPENS_ABOVE || !(first?.k === "word" && JS_RESERVED.has(first.v));
}

/**
 * Whether token `p` ends a decorator whose "@" its text holds: true, false, AT_START (the walk reached the text's
 * start, so the "@" or what the expression goes on from may stand above it), or OPENS_ABOVE (a bracket that opens
 * above the text, as a call's arguments over lines do, or type arguments read back to its start or past
 * DECORATOR_TOKENS). As TypeScript reads a
 * decorator, its expression is an operand (a name, a group, any bracket or literal: @name, @(expr), @[x], @'x',
 * @/re/), then any number of ".name", "?.name", "?.[x]", "!", a call's arguments, type arguments before them and a
 * tagged template: @a.b, @Dec(), @Dec()(), @Dec<T>(), @(Dec), @(a.b)(), @a?.b!.c(), @tag`x`, @new Foo(), and any of
 * them over lines (@\nDec(), @a\n.b()). A name may be written with escapes (@\u0044ec(), lexed as a word). A "["
 * after it is the member's computed name, never a subscript. Each step moves back, over a group whole, so a walk
 * costs at most its expression's tokens and DECORATOR_TOKENS per type argument list.
 */
function decoratorWalk(r, p) {
  const { toks } = r;
  for (let k = p; ; ) {
    if (k < 0) return AT_START;
    const t = toks[k];
    if (t.k === "close") {
      // A group right after "@", or a call's arguments (what is called stands before them, maybe after an optional
      // call's "?."), or an optional subscript's (?.[x]).
      const open = r.opener[k];
      if (open < 0) return OPENS_ABOVE;
      const q = back(r, open);
      if (q < 0) return AT_START;
      if (isOp(toks[q], "@")) return true;
      if (t.v === ")") k = isOp(toks[q], "?.") ? back(r, q) : q;
      else if (t.v === "]" && isOp(toks[q], "?.")) k = back(r, q);
      else return false;
    } else if (isOp(t, ">") || isOp(t, ">>") || isOp(t, ">>>")) {
      // Type arguments before a call: Dec<T>().
      const open = angleStart(r, k);
      if (open === UNFINISHED) return OPENS_ABOVE;
      if (open < 0) return false;
      k = back(r, open);
    } else if (isOp(t, "!")) {
      // A non-null "!" follows what it is applied to.
      k = back(r, k);
    } else if (t.k === "str" || t.k === "num" || t.k === "regex") {
      // A literal right after "@", or a tagged template after what it tags.
      const q = back(r, k);
      if (q < 0) return AT_START;
      if (isOp(toks[q], "@")) return true;
      if (!(t.k === "str" && t.fence === "`")) return false;
      k = q;
    } else if (t.k === "word") {
      const q = back(r, k);
      if (q < 0) return AT_START;
      if (isOp(toks[q], "@")) return true;
      if (isWord(toks[q], "new")) k = q;
      else if (isOp(toks[q], ".") || isOp(toks[q], "?.")) k = back(r, q);
      else return false;
    } else return false;
  }
}

/**
 * The "<" that opens the type arguments whose last ">" is token `k` (">>" and ">>>" close two and three, "<<" opens
 * two, a group is passed over whole), read back; -1 where none opens first (a statement's end, a group that opens
 * around it, an assignment, or a comparison glued to "="), or UNFINISHED where a group opens above the text, or the
 * list runs back to the text's start or past DECORATOR_TOKENS.
 */
function angleStart(r, k) {
  const { toks } = r;
  let depth = 0;
  for (let j = k; j >= 0; j--) {
    if (k - j > DECORATOR_TOKENS) return UNFINISHED;
    const t = toks[j];
    if (t.k === "close") {
      if (r.opener[j] < 0) return UNFINISHED;
      j = r.opener[j];
    } else if (t.k === "open" || t.k === "semi" || t.k === "end") return -1;
    else if (t.k === "op") {
      if (t.v === ">" || t.v === ">>" || t.v === ">>>") depth += t.v.length;
      else if (t.v === "<" || t.v === "<<") {
        depth -= t.v.length;
        if (depth === 0) return j;
        if (depth < 0) return -1;
      } else if (ASSIGN.has(t.v) || t.v === ">=" || t.v === "<=" || t.v === ">>=" || t.v === "<<=") return -1;
    }
  }
  return UNFINISHED;
}

/** The token after the "=" that ends a declaration's type from token `k` ("<" and ">" nest), or -1 when the statement ends first. JavaScript's is read by tsType. */
function afterType(r, k) {
  const { toks, match } = r;
  for (let angle = 0; k < toks.length; k++) {
    const t = toks[k];
    if (t.k === "open") {
      if (match[k] < 0) return -1;
      k = match[k];
    } else if (isOp(t, "<")) angle++;
    else if (isOp(t, ">")) angle--;
    else if (isOp(t, ">>")) angle -= 2;
    else if (isOp(t, "=") && angle <= 0) return k + 1;
    else if (t.k === "semi" || t.k === "close" || t.k === "end" || ((t.k === "comma" || t.k === "nl") && angle <= 0)) return -1;
  }
  return -1;
}

/** Python's self.password: str = ...: an attribute's annotation at its statement's start. */
function attributeAnnotation(r, i) {
  let k = back(r, i, false);
  if (!isOp(r.toks[k], ".")) return false;
  while (k >= 0 && isOp(r.toks[k], ".")) {
    const w = back(r, k, false);
    if (w < 0 || r.toks[w].k !== "word") return false;
    k = back(r, w, false);
  }
  return k < 0 || r.toks[k].k === "nl";
}

// ----- TypeScript types -------------------------------------------------------------------------

/** Words before a TypeScript type's operand: keyof T, typeof x, readonly T[], unique symbol, infer U, asserts x. */
const TYPE_PREFIX = new Set(["keyof", "typeof", "readonly", "unique", "infer", "asserts"]);

/**
 * What the TypeScript readers below give where the text ends before what they read does (a type, a type parameter
 * list, a function type's "=>"): the next line may finish it. Where the text cannot go on (the change ends there,
 * or the line is all that is known), it is open, and counts. -1 is syntax the reader cannot read, which counts.
 */
const UNFINISHED = -2;

/**
 * The token after the type parameter or argument list whose "<" is token `k` ("<" and ">" nest, "<<" opens two,
 * ">>" and ">>>" close two and three, and a group is passed over whole), -1 when it does not close first (a
 * statement's end, a bracket that closes around it, or a close glued to an "=" (">=", ">>=") comes before), or
 * UNFINISHED when the text ends first.
 */
function angleEnd(r, k) {
  const { toks, match } = r;
  let depth = 0;
  for (let j = k; j < toks.length; j++) {
    const t = toks[j];
    if (t.k === "open") {
      if (match[j] < 0) return UNFINISHED;
      j = match[j];
      continue;
    }
    if (t.k === "semi" || t.k === "close" || t.k === "end") return -1;
    if (t.k !== "op") continue;
    if (t.v === "<") depth++;
    else if (t.v === "<<") depth += 2;
    else if (t.v === ">" || t.v === ">>" || t.v === ">>>") {
      depth -= t.v.length;
      if (depth === 0) return j + 1;
      if (depth < 0) return -1;
    } else if (t.v === ">=" || t.v === ">>=" || t.v === "<=" || t.v === "<<=") return -1;
  }
  return UNFINISHED;
}

/** What waits on tsType's stack for the type being read: an extends clause's reading to keep (KEEP), a union's next operand (UNION), a conditional's "?" (CHECK) or ":" (BRANCH), and a constrained infer's verdict (INFER). */
const KEEP = 0;
const UNION = 1;
const CHECK = 2;
const BRANCH = 3;
const INFER = 4;

/**
 * The token after the TypeScript type that starts at token `k` (JavaScript's reading), -1 when it cannot read one,
 * or UNFINISHED when the text ends inside it. A type is operands joined by "|" and "&" (one may lead them), or a
 * conditional (T extends U ? X : Y). An operand is a name with its path and type arguments (Map<string, string>,
 * import("x").Y), a literal (a string, a number, -1, a template), an object type {...}, a tuple [...], a group or a
 * function type ((x: T) => R, <T>(x: T) => R, new (...) => R), or a prefix's operand (keyof, typeof, readonly,
 * unique, infer, asserts); "[]" and "[K]" follow one, and a predicate's "x is T". Line breaks inside a type are
 * read through. A conditional's extends clause (`clause`: the U of T extends U ? X : Y) holds no conditional, as
 * TypeScript reads it: an "extends" there ends the type, and infer V extends C reads C as V's constraint (tsOperand).
 *
 * It reads without recursion, so a type is read whole however deep its conditionals, function types and predicates
 * nest: what is still to come waits on a stack (a union's next operand, a conditional's "?", ":" and branch, a
 * constrained infer's verdict), and where each extends clause read from a token ends is kept per reader (r.types),
 * so a constraint read again (infer V extends C ? ..., read as V's and then as the conditional's) is not read twice,
 * and a reading costs its type's tokens once.
 */
function tsType(r, k, clause = false) {
  const { toks } = r;
  const stack = [];
  // A type still to read from token `at` (as an extends clause when `inClause`), or with `at` -1 a reading's end to
  // hand to what waits on the stack.
  let at = k;
  let inClause = clause;
  let end = 0;
  for (;;) {
    if (at >= 0) {
      // Only an extends clause is ever read twice from one token (a constrained infer's, then its conditional's).
      if (inClause) {
        const seen = r.types.get(at);
        if (seen !== undefined) {
          end = seen;
          at = -1;
          continue;
        }
        stack.push(KEEP, at);
      }
      let j = skip(r, at, true);
      if (isOp(toks[j], "|") || isOp(toks[j], "&")) j = skip(r, j + 1, true);
      stack.push(UNION, inClause ? 1 : 0);
      const next = tsOperand(r, j, inClause, stack);
      if (typeof next === "number") {
        end = next;
        at = -1;
      } else ({ at, clause: inClause } = next);
      continue;
    }
    if (!stack.length) return end;
    const arg = stack.pop();
    const kind = stack.pop();
    if (kind === KEEP) {
      r.types.set(arg, end);
      continue;
    }
    if (kind === INFER) {
      // The constraint of infer V at token arg >> 1: in an extends clause always V's; elsewhere V's only where no "?"
      // follows it, since infer V extends C ? X : Y is a conditional there.
      if (!(arg & 1) && end !== UNFINISHED) end = end >= 0 && !isOp(toks[skip(r, end, true)], "?") ? end : (arg >> 1) + 1;
      continue;
    }
    if (end < 0) continue;
    if (kind === UNION) {
      const n = skip(r, end, true);
      if (isOp(toks[n], "|") || isOp(toks[n], "&")) {
        stack.push(UNION, arg);
        const next = tsOperand(r, skip(r, n + 1, true), arg === 1, stack);
        if (typeof next === "number") end = next;
        else ({ at, clause: inClause } = next);
      } else if (isWord(toks[n], "extends") && arg === 0) {
        // Each part read in turn; the text's end before one of them leaves the type unfinished. The extends clause
        // holds no conditional; each branch may hold one.
        stack.push(CHECK, 0);
        at = n + 1;
        inClause = true;
      }
      continue;
    }
    const q = skip(r, end, true);
    if (!isOp(toks[q], kind === CHECK ? "?" : ":")) {
      end = q >= toks.length ? UNFINISHED : -1;
      continue;
    }
    // After the "?" the branch, then after the ":" the other, whose end is the conditional's.
    if (kind === CHECK) stack.push(BRANCH, 0);
    at = q + 1;
    inClause = false;
  }
}

/**
 * The function type whose parameters' "(" is token `open`: { at } for the type after its "=>" (which tsType reads
 * on, and whose end is the function type's), -1, or UNFINISHED.
 */
function functionTypeEnd(r, open) {
  if (open >= r.toks.length) return UNFINISHED;
  if (r.toks[open].v !== "(") return -1;
  if (r.match[open] < 0) return UNFINISHED;
  const arrow = skip(r, r.match[open] + 1, true);
  if (arrow >= r.toks.length) return UNFINISHED;
  return isOp(r.toks[arrow], "=>") ? { at: arrow + 1, clause: false } : -1;
}

/**
 * The token after one TypeScript type operand from token `j` (tsType), with its postfixes, -1, or UNFINISHED; or
 * { at, clause } where the operand ends where a type from token `at` does (a function type's return type, a
 * predicate's type, or an inferred name's constraint, whose verdict waits on tsType's `stack`). An inferred name
 * may have a constraint: infer V extends C. In a conditional's extends clause (`clause`) C is always read as V's;
 * elsewhere only where no "?" follows C, since infer V extends C ? X : Y is a conditional there.
 */
function tsOperand(r, j, clause, stack) {
  const { toks, match } = r;
  let t = toks[j];
  if (!t) return UNFINISHED;
  // A prefix stands before an operand; followed by anything else it is a name.
  let inferred = false;
  while (isWord(t) && TYPE_PREFIX.has(t.v)) {
    const n = skip(r, j + 1, true);
    const after = toks[n];
    if (!after || !(after.k === "word" || after.k === "open" || after.k === "str" || after.k === "num" || isOp(after, "<") || isOp(after, "-"))) break;
    inferred = isWord(t, "infer") && after.k === "word";
    j = n;
    t = after;
  }
  if (inferred) {
    // infer V, maybe with its constraint; a name's path, type arguments and "[]" never follow V.
    const ext = skip(r, j + 1, true);
    if (!isWord(toks[ext], "extends")) return j + 1;
    stack.push(INFER, 2 * j + (clause ? 1 : 0));
    return { at: ext + 1, clause: true };
  }
  let end;
  if (isWord(t, "new") || (isWord(t, "abstract") && isWord(toks[skip(r, j + 1, true)], "new"))) {
    // A constructor type: new (...) => T, maybe with its type parameters.
    let k = skip(r, (isWord(t, "new") ? j : skip(r, j + 1, true)) + 1, true);
    if (isOp(toks[k], "<")) {
      const a = angleEnd(r, k);
      if (a < 0) return a;
      k = skip(r, a, true);
    }
    return functionTypeEnd(r, k);
  }
  if (t.k === "word") {
    end = j + 1;
    // import("x"), a path of names, then type arguments, and a predicate's "is T". As TypeScript reads them, an
    // import's "(" and a path's "." may stand on the next line (A\n.B), and its "<", "is" and "[" may not.
    if (isWord(t, "import") && toks[skip(r, end, true)]?.v === "(") {
      const open = skip(r, end, true);
      if (match[open] < 0) return UNFINISHED;
      end = match[open] + 1;
    }
    for (let k = skip(r, end, true); isOp(toks[k], "."); k = skip(r, end, true)) {
      // A path's next name, maybe on the next line.
      const w = skip(r, k + 1, true);
      if (w >= toks.length) return UNFINISHED;
      if (toks[w].k !== "word") break;
      end = w + 1;
    }
    const lt = skip(r, end);
    if (isOp(toks[lt], "<")) {
      end = angleEnd(r, lt);
      if (end < 0) return end;
    }
    const is = skip(r, end);
    if (isWord(toks[is], "is")) return { at: is + 1, clause: false };
  } else if (t.k === "str" || t.k === "num") {
    // A string the text ends inside (a template over lines) is unfinished; one a line break cut is not read.
    if (t.k === "str" && !t.closed) return t.e >= r.text.length ? UNFINISHED : -1;
    end = j + 1;
  } else if (isOp(t, "-") && toks[j + 1]?.k === "num") {
    end = j + 2;
  } else if (t.k === "open") {
    if (match[j] < 0) return UNFINISHED;
    end = match[j] + 1;
    // A group may be a function type's parameters.
    if (t.v === "(" && isOp(toks[skip(r, end, true)], "=>")) return functionTypeEnd(r, j);
  } else if (isOp(t, "<")) {
    // A generic function type: <T>(x: T) => T.
    const a = angleEnd(r, j);
    return a < 0 ? a : functionTypeEnd(r, skip(r, a, true));
  } else return -1;
  // T[] and T[K].
  for (let k = skip(r, end); toks[k]?.k === "open" && toks[k].v === "["; k = skip(r, end)) {
    if (match[k] < 0) return UNFINISHED;
    end = match[k] + 1;
  }
  return end;
}

/** Whether a line break stands among tokens [a, b). */
function breakBetween(r, a, b) {
  for (let k = a; k < b; k++) if (r.toks[k].k === "nl") return true;
  return false;
}

/**
 * What follows a TypeScript declaration's type, which ends at token `end` (tsType): { value } (the token after its
 * "=", maybe on the next line), { none } (the declaration ends: ";", ",", a closing bracket, "?>" or a line break),
 * { more } (the text ends after the type), { open } (the text ends inside the type: UNFINISHED), or { unread }
 * (a type the reader cannot read, or anything else after it).
 */
function afterDeclaredType(r, end) {
  if (end === UNFINISHED) return { open: true };
  if (end < 0) return { unread: true };
  const n = skip(r, end, true);
  const t = r.toks[n];
  if (!t) return { more: true };
  if (isOp(t, "=")) return { value: n + 1 };
  if (t.k === "semi" || t.k === "comma" || t.k === "close" || t.k === "end" || breakBetween(r, end, n)) return { none: true };
  return { unread: true };
}

/** A declaration's initializer: any assignment. */
const INITIALIZER = ASSIGN;
/** An arrow function's "=>". */
const ARROW = new Set(["=>"]);

/**
 * Where the item that runs from token `k` stops: at the first of the operators `ops`, or at its end (a "," outside a
 * "<...>" list, a ";", a bracket that closes around it, or PHP's "?>"), over groups: { at, angle, hidden } (the
 * token; how many "<" are open there; whether one of `ops` stood where a "<" was open, since the last token where
 * none was), or null when the text ends first. "<" and ">" are counted, not matched. It is asked where the reader
 * could not read a type, so it cannot tell where the type ends, and a line break ends nothing: a type goes on over
 * lines after a complete operand (infer U\nextends C, abstract\nnew), so an initializer or a "=>" on any later line of
 * the item is found, and one the next statement holds (ASI) counts with it. A reading from a token where no "<" is
 * open goes on as one that starts there does, so what each such token gives is kept per `ops` (r.items), and many
 * names in one long item read each token once.
 */
function itemEnd(r, k, ops) {
  const { toks, match } = r;
  let known = r.items.get(ops);
  if (!known) r.items.set(ops, (known = new Map()));
  const passed = [];
  let angle = 0;
  let hidden = false;
  let end = null;
  for (let j = k; j < toks.length; j++) {
    if (angle === 0) {
      const seen = known.get(j);
      if (seen !== undefined) {
        end = seen;
        break;
      }
      passed.push(j);
      hidden = false;
    }
    const t = toks[j];
    if (t.k === "open") {
      if (match[j] < 0) break;
      j = match[j];
    } else if (t.k === "semi" || t.k === "close" || t.k === "end" || (t.k === "comma" && angle <= 0)) {
      end = { at: j, angle, hidden };
      break;
    } else if (t.k === "op") {
      if (t.v === "<" || t.v === "<<") angle += t.v.length;
      else if (t.v === ">" || t.v === ">>" || t.v === ">>>") angle -= t.v.length;
      else if (angle <= 0 && ops.has(t.v)) {
        end = { at: j, angle, hidden };
        break;
      } else if (ops.has(t.v)) hidden = true;
    }
  }
  for (const j of passed) known.set(j, end);
  return end;
}

/**
 * Whether one of the operators `ops` stands in the item that runs from token `k` (itemEnd): true, false, or UNFINISHED
 * when the text ends first. Neither a declaration's initializer nor an arrow's "=>" can stand inside a group or a
 * "<...>" list (where "=" is a type parameter's default), so a type the reader cannot read hides neither from this.
 * Where "<" and ">" do not balance at the item's end, an initializer that stood where a "<" was open may be one that
 * no list holds ((A) B < C = '...'), and it counts too. An arrow's "=>" does not count so: a JavaScript comparison
 * leaves a "<" open before one (ok ? (a) : n < m ? x => x : y).
 */
function itemHolds(r, k, ops) {
  const end = itemEnd(r, k, ops);
  if (!end) return UNFINISHED;
  const t = r.toks[end.at];
  return (t.k === "op" && ops.has(t.v)) || (ops === INITIALIZER && end.angle !== 0 && end.hidden);
}

/** What arrowParams gives where the text ends right after an arrow's whole return type and the next line may be read: the type may go on. */
const AFTER_RETURN_TYPE = "return type";

/**
 * Whether the "(" at token `open` is an arrow function's parameters: "=>" after them, or a return type and "=>";
 * UNFINISHED when the text ends inside the parameters or the return type, and no call's arguments are them (a
 * name, a close or a string before the "("; async excepted). No line break may stand before an arrow's "=>", so
 * a text that ends after the parameters has none. One that ends right after a whole return type may go on with
 * the type on the next line (A\n| B, abstract\nnew () => R, A\n.B, infer U\nextends C), and its "=>" after it, so
 * where the next line may be read (the text's end is "more") and the type holds no literal it is AFTER_RETURN_TYPE,
 * read on as a value its line leaves unended. "(a) : x" is also a ternary's branch, which a line that ends there
 * leaves unended too, so this reads it as before; one that holds a literal ((a) : 'v1') or that the change ends
 * after is read as before, and the literal counts. -1: an arrow whose return type the reader cannot read, or reads
 * followed by anything but "=>", while a "=>" stands later in the item (itemHolds); it counts.
 */
function arrowParams(r, open) {
  const { toks, match } = r;
  if (toks[open]?.v !== "(") return false;
  const p = toks[back(r, open)];
  const call = Boolean(p) && (p.k === "close" || p.k === "str" || (p.k === "word" && !isWord(p, "async")));
  if (match[open] < 0) return call ? false : UNFINISHED;
  const k = skip(r, match[open] + 1, true);
  if (isOp(toks[k], "=>")) return true;
  if (!isOp(toks[k], ":")) return false;
  const e = tsType(r, k + 1);
  if (e === UNFINISHED) return call ? false : UNFINISHED;
  const after = e >= 0 ? skip(r, e, true) : -1;
  if (after >= 0 && isOp(toks[after], "=>")) return true;
  if (after >= toks.length && !call && r.end === "more" && !toks.slice(k + 1, e).some((t) => t.k === "str" || t.k === "num" || t.k === "regex")) return AFTER_RETURN_TYPE;
  return !call && itemHolds(r, e >= 0 ? e : k + 1, ARROW) === true ? -1 : false;
}

/** A quoted or computed name that a password names. */
const QUOTED_NAME = /^[\w-]{0,200}pass(?:word|wd)$/i;

/**
 * The text of the computed name in the group that opens at `open` ([...]) when it is static: strings with no
 * reference or interpolation in them, joined by the language's join (in Python also side by side), at most
 * 200 characters: ['password'], [`password`], ['db_' + 'password']. Otherwise null.
 */
function staticName(r, open) {
  const { toks, match, L } = r;
  const close = match[open];
  if (close < 0) return null;
  let text = "";
  let pieces = 0;
  let joined = true;
  for (let k = skip(r, open + 1, true); k < close; k = skip(r, k + 1, true)) {
    const t = toks[k];
    if (t.k === "str" && t.closed && (joined || L.id === "py")) {
      const value = partsValue(partsOf(t));
      if (value.bad || value.refs || text.length + value.text.length > 200) return null;
      text += value.text;
      pieces++;
      joined = false;
    } else if (pieces && !joined && isOp(t, L.join)) joined = true;
    else return null;
  }
  return pieces && !joined ? text : null;
}

/**
 * The targets on the text's first line, last first (so that a chain's later links are read first):
 * a name given a value by an assignment, a key's ":" or "=>", a subscript's assignment
 * ($cfg['password'] = ...) or define('DB_PASSWORD', ...). A name is a word, a quoted name or a computed
 * one whose text is static (staticName: { ['password']: ... }, class C { ['password']() {...} }).
 */
function targetsOf(r) {
  const { toks, L } = r;
  const found = [];
  for (let i = 0; i < toks.length && toks[i].s < r.firstEnd; i++) {
    const t = toks[i];
    const computed = t.k === "open" && t.v === "[" ? staticName(r, i) : null;
    // A quoted name with its escapes read ('p\x61ssword', "\u0070assword"); a word's are read by the lexer (wordName).
    const quoted = t.k === "str" && t.closed ? decodeName(t.body) : null;
    const name =
      t.k === "word" && PASSWORD_NAME.test(t.v)
        ? t.v
        : quoted !== null && QUOTED_NAME.test(quoted)
          ? quoted
          : computed !== null && QUOTED_NAME.test(computed)
            ? computed
            : null;
    if (name === null) continue;
    // A quoted or computed name: JavaScript's method, getter, class field and object key may be spelled so.
    const spelled = t.k !== "word";
    let n = skip(r, (computed !== null ? r.match[i] : i) + 1, r.end !== "line");
    let next = toks[n];
    const p = back(r, i);
    const prev = toks[p];
    // TypeScript: an optional or definite marker after the name (password?: T, password!: T, password?() {...}). One
    // the text ends after may be read on the next line (password?\n: string = ...).
    let marked = false;
    if (L.id === "js" && (isOp(next, "?") || isOp(next, "!"))) {
      const m = skip(r, n + 1, r.end !== "line");
      if (m >= toks.length) {
        if (r.end === "more") found.push({ more: true });
        continue;
      }
      if (isOp(toks[m], ":") || (isOp(next, "?") && (toks[m]?.v === "(" || isOp(toks[m], "<")))) {
        marked = true;
        n = m;
        next = toks[m];
      }
    }
    // A generic parameter list before a declaration's parameters: function password<T>(), password<T>() {...}, and
    // Python's def password[T](). Where the text ends inside the list or right after it, a declaration's name is read
    // on (the next lines may finish it: password<\nT\n>() {...}), and where the text cannot go on it is open. One that
    // a function's or a def's name has and the reader cannot read counts. Where a key stands the "<" may compare
    // (password < max): one spaced from the name is read on only.
    if ((L.id === "js" && isOp(next, "<")) || (L.id === "py" && next?.v === "[" && isWord(prev, "def"))) {
      const a = L.id === "js" ? angleEnd(r, n) : r.match[n] < 0 ? UNFINISHED : r.match[n] + 1;
      const m = a < 0 ? a : skip(r, a, true);
      if (m >= 0 && toks[m]?.v === "(") {
        n = m;
        next = toks[m];
      } else if ((a === UNFINISHED || m >= toks.length) && declarationName(r, i, p)) {
        const spaced = declarationName(r, i, p) === "key" && next.s > toks[back(r, n, false)].e;
        if (!spaced) found.push({ open: i });
        else if (r.end === "more") found.push({ more: true });
        continue;
      } else if (isWord(prev, "function") || isWord(prev, "def") || (isOp(prev, "*") && isWord(toks[back(r, p)], "function"))) {
        found.push({ unread: true });
        continue;
      }
    }
    // A name the text ends after may be given its value on the next line (password\n = ..., setPassword\n(...)).
    if (!next && r.end === "more") found.push({ more: true });
    if (!next || isWord(prev, "case")) continue;
    if (marked && isOp(next, ":")) {
      // An optional or definite declaration's value is after its type's "=", or there is none.
      found.push({ declared: afterDeclaredType(r, tsType(r, n + 1)), at: i, field: r.parent[i], flag: FLAG_NAME.test(name), helper: isHelper(name), key: true });
      continue;
    }
    const helper = isHelper(name);
    const add = (value, reading, extra = {}) => found.push({ value, field: r.parent[i], reading, flag: FLAG_NAME.test(name), helper, ...extra });
    const group = r.parent[i];
    if ((L.id === "php" || r.end === "comment") && t.k === "word" && !t.v.startsWith("$") && isOp(next, "=") && n === i + 1 && toks[n + 1] && toks[n + 1].s === next.e && !isOp(prev, "->") && !isOp(prev, "::") && !isWord(prev, "const")) {
      // PHP gives no bare name a value, so NAME=word there (a heredoc's shell line) is a shell assignment; so is one in a comment.
      found.push({ shell: next.e, field: -1, flag: false });
    } else if (t.k === "word" && next.k === "op" && ASSIGN.has(next.v)) {
      // In Python a statement's own commas make a tuple: user, password = ..., ... reads all of it.
      add(n + 1, undefined, { tuple: L.id === "py" && group < 0 });
    } else if (t.k === "word" && group >= 0 && (next.k === "comma" || n === r.match[group]) && r.match[group] > 0 && isOp(toks[skip(r, r.match[group] + 1)], "=") && listPattern(r, group)) {
      // A name in a list pattern ([user, password] = ..., list($user, $password) = ...): all of the value.
      add(skip(r, r.match[group] + 1) + 1, undefined, { tuple: L.id === "py" });
    } else if (t.k === "word" && next.k === "close" && (group < 0 || r.match[group] !== n)) {
      // The last name of a list pattern whose bracket opens on a line before (user,\n password] = ...).
      let k = n;
      while (toks[k]?.k === "close" && (r.parent[k] < 0 || r.match[r.parent[k]] !== k)) k = skip(r, k + 1);
      if (k > n && isOp(toks[k], "=")) add(k + 1, undefined, { tuple: L.id === "py" });
    } else if ((t.k === "word" || (spelled && L.id === "js")) && next.k === "open" && next.v === "(" && !helper && accessorAt(r, i, p, n)) {
      // An accessor's value is what it returns: get password() {...}, function getPassword() {...}, def password(self): ...,
      // and in JavaScript a quoted or computed one: get 'password'() {...}, ['password']() {...}.
      // A method the text ends in before its body shows (password(\n...) or password()\n{...}) may be read on.
      if (accessorAt(r, i, p, n) === UNFINISHED) {
        if (r.end === "more") found.push({ more: true });
        continue;
      }
      found.push({ accessor: n, def: p, field: -1, flag: false });
    } else if (spelled && next.k === "op" && ASSIGN.has(next.v) && (computed !== null || (L.id === "js" && keyPosition(r, i)))) {
      // A computed subscript's or key's assignment (cfg['db_' + 'password'] = ..., class C { ['password'] = ... }), and
      // a JavaScript class field with a quoted name (class C { 'password' = ... }). A subscript's group is declared by
      // what it subscripts ($labels['pass' . 'word'] = ...).
      add(n + 1, undefined, computed !== null ? { field: i } : {});
    } else if (t.k === "word" && SETTER_NAME.test(t.v) && (next.k === "open" ? next.v === "(" : isOp(next, "?.") && toks[skip(r, n + 1, true)]?.v === "(")) {
      // A setter's arguments are the password's value: setPassword(...), withPassword (...), setPassword/* c */(...),
      // setPassword?.(...).
      found.push({ args: next.k === "open" ? n : skip(r, n + 1, true), field: -1, flag: false });
    } else if (t.k === "str" && next.k === "comma" && toks[skip(r, n + 1, true)]?.k !== "close" && (group >= 0 ? setterCall(r, group) : outerOf(r).setter)) {
      // set('password', '...'), setattr(cfg, 'password', '...'), localStorage.setItem('password', '...'), and the same
      // call opened on a line above, the name and its value on the lines after it.
      add(n + 1, undefined, { item: true });
    } else if (isOp(next, ":") && t.k === "word" && isWord(prev) && DECLARING.has(prev.v)) {
      // A declaration's type (let pw: Record<string, string> = ...): the value is after its "=", or there is none. In
      // JavaScript the whole type is read (tsType), and one it cannot read counts.
      if (L.id === "js") found.push({ declared: afterDeclaredType(r, tsType(r, n + 1)), at: i, field: r.parent[i], flag: FLAG_NAME.test(name), helper });
      else {
        const value = afterType(r, n + 1);
        if (value >= 0) add(value);
      }
    } else if (isOp(next, ":") && (keyPosition(r, i) || (L.id === "py" && t.k === "word" && attributeAnnotation(r, i)))) {
      // PHP gives values by "=" and "=>": a key's ":" there is a named argument.
      add(n + 1, undefined, { key: true });
      // In JavaScript the same "name:" is a class field's or a parameter's type when a whole type and an "=" follow it
      // (password: Map<string, string> = ...): the value after the "=" is read too. A type the text ends inside or
      // after may go on to its "=" on the next line (password: Map<string,\n string> = ...); after a member's
      // modifier or a decorator the name is a field's, and one the text ends inside is open. Where the reader cannot
      // read the type, an assignment in the item (itemHolds) gives the field or the parameter a value it cannot read,
      // and counts; a key's value that is no type (an object's: password: getPw()) holds none, and is read above. One
      // the text ends inside, before any, reads on as a value left unended does.
      if (L.id === "js") {
        const declared = afterDeclaredType(r, tsType(r, n + 1));
        if (declared.value !== undefined) add(declared.value, undefined, { key: true });
        else if (declared.open && memberModified(r, p)) found.push({ open: i });
        else if ((declared.open || declared.more) && r.end === "more") found.push({ more: true });
        else if (declared.unread) {
          const assigned = itemHolds(r, n + 1, INITIALIZER);
          if (assigned === true) found.push({ unread: true });
          else if (assigned === UNFINISHED) found.push({ declared: { more: true }, at: i });
        }
      }
    } else if (t.k === "str" && isOp(next, "=>")) {
      add(n + 1, undefined, { key: true });
    } else if (t.k === "str" && prev?.k === "open" && prev.v === "[" && next.k === "close" && next.v === "]") {
      const op = skip(r, n + 1);
      if (toks[op]?.k === "op" && ASSIGN.has(toks[op].v)) add(op + 1);
    } else if (t.k === "str" && prev?.k === "open" && prev.v === "(" && isWord(toks[back(r, p)], "define") && next.k === "comma") {
      add(n + 1);
    }
  }
  return found.reverse();
}

/** Whether the group that opens at `open` is the arguments of a call handed a name and then its value: set(...), define(...). */
function setterCall(r, open) {
  const callee = r.toks[back(r, open)];
  return r.toks[open].v === "(" && isWord(callee) && (SETTER_CALLS.has(callee.v) || isWord(callee, "define"));
}

/** A helper's name: a verb before the password (validatePassword, check_password, togglePassword). A function it is given returns no password. */
const HELPER_VERB = /^(?:validate|valid|check|verify|is|has|can|should|compare|match|matches|test|toggle|show|hide|on|handle|render|set|update|change|reset|save|store|send|submit|clear|focus|mask|unmask|strength|score|require|remember|allow|enable|disable|forgot|lost)(?=[A-Z_0-9])/;
const isHelper = (name) => {
  const bare = name.replace(/^[$@]+/, "");
  return HELPER_VERB.test(bare.charAt(0).toLowerCase() + bare.slice(1));
};
/** Words before a JavaScript method's name. */
const METHOD_MODIFIERS = new Set(["async", "static", "public", "private", "protected", "readonly", "override", "abstract"]);

/**
 * Whether the name at token `i` (`p` the token before it) stands where a declaration's name does, in JavaScript and
 * Python: "declaration" after function (and a generator's "*"), def, get or set, a method's modifier or a generator
 * method's "*"; "key" where a key does (keyPosition), which an expression's name may also stand at; or false.
 */
function declarationName(r, i, p) {
  const { toks, L } = r;
  const prev = toks[p];
  if (isWord(prev, "function") || isWord(prev, "def") || (isOp(prev, "*") && isWord(toks[back(r, p)], "function"))) return "declaration";
  if (L.id !== "js") return false;
  if (isWord(prev, "get") || isWord(prev, "set") || (isWord(prev) && METHOD_MODIFIERS.has(prev.v)) || (isOp(prev, "*") && keyPosition(r, p))) return "declaration";
  return keyPosition(r, i) ? "key" : false;
}

/**
 * Whether the name at token `i` (`p` the token before it, `n` its "(", after a TypeScript marker or generic list)
 * declares an accessor: a function or a method of PHP and JavaScript (function password(...), function
 * &password(...)), Python's def, and in JavaScript a getter (get password() {...}) or a method (password() {...},
 * async password() {...}). A call is none: a JavaScript method is one only where a body follows its parameters and
 * its whole return type (tsType); one whose return type the reader cannot read is one, and counts (accessorValue).
 * After a modifier or a generator's "*" the name is a method's; where a key stands, it may be a call. UNFINISHED:
 * the text ends before a body shows (inside the parameters or the return type, or after them), so the next line
 * may make the name a method's.
 */
function accessorAt(r, i, p, n) {
  const { toks, L } = r;
  const prev = toks[p];
  if (L.id === "py") return isWord(prev, "def");
  if (isWord(prev, "function") || ((isOp(prev, "&") || isOp(prev, "*")) && isWord(toks[back(r, p)], "function"))) return true;
  if (L.id !== "js") return false;
  // "get" before a name and its "(" is a getter wherever it stands (after a decorator too).
  if (isWord(prev, "get")) return true;
  const method = (isWord(prev) && METHOD_MODIFIERS.has(prev.v)) || (isOp(prev, "*") && keyPosition(r, p));
  if (!(keyPosition(r, i) || method || isOp(prev, "*"))) return false;
  if (r.match[n] < 0) return method ? true : UNFINISHED;
  let k = skip(r, r.match[n] + 1, true);
  if (isOp(toks[k], ":")) {
    // The whole return type (string & { length: number }): a call has none, so this is a method. One whose type the
    // reader cannot read counts, and one the text ends inside is open (accessorValue).
    const e = tsType(r, k + 1);
    if (e < 0) return true;
    k = skip(r, e, true);
  }
  if (k >= toks.length) return UNFINISHED;
  return toks[k].k === "open" && toks[k].v === "{";
}

/** The token after a PHP or Python return type from token `k` (?string, A|B, dict[str, str]): the body's "{", Python's ":", or what ends it. TypeScript's is read by tsType. */
function typeEnd(r, k) {
  const { toks, match, L } = r;
  while (k < toks.length && !isGroupEnd(toks[k]) && !(toks[k].k === "open" && toks[k].v === "{") && !(L.id === "py" && isOp(toks[k], ":"))) {
    if (toks[k].k === "open") {
      if (match[k] < 0) return toks.length;
      k = match[k];
    }
    k = skip(r, k + 1, true);
  }
  return k;
}

/**
 * What an accessor returns (accessorAt; `open` its parameters' "(", `def` the token before its name): its
 * parameters' defaults (paramsHoldLiteral) and its body's returns (bodyValue, pythonBody), their strings read by
 * `reading` (a PHP page's script's). A declaration without a body (an interface's, an abstract method) returns nothing
 * but its defaults. { lit }, { open } or {}.
 */
function accessorValue(r, open, def, reading) {
  const { toks, match, L } = r;
  if (match[open] < 0) return { open: true };
  if (paramsHoldLiteral(r, open, 0, reading)) return { lit: true };
  let k = skip(r, match[open] + 1, true);
  if (L.id === "js" && isOp(toks[k], ":")) {
    // TypeScript's return type is read whole (string & { length: number }): one the reader cannot read counts, and
    // one the text ends inside is open.
    const e = tsType(r, k + 1);
    if (e === UNFINISHED) return { open: true };
    if (e < 0) return { lit: true };
    k = skip(r, e, true);
    if (k >= toks.length) return { open: true };
    if (toks[k].k === "open" && toks[k].v === "{") return bodyValue(r, k, 0, reading);
    // A declaration without a body (an overload, an interface's or an abstract method) ends; anything else counts.
    const t = toks[k];
    return t.k === "semi" || t.k === "comma" || t.k === "close" || t.k === "end" || breakBetween(r, e, k) ? {} : { lit: true };
  }
  if (isOp(toks[k], L.id === "py" ? "->" : ":")) k = typeEnd(r, skip(r, k + 1, true));
  if (k >= toks.length) return { open: true };
  if (L.id === "py") return isOp(toks[k], ":") ? pythonBody(r, def, k) : {};
  return toks[k].k === "open" && toks[k].v === "{" ? bodyValue(r, k, 0, reading) : {};
}

/**
 * A Python def's body after its ":" at token `colon`: the rest of its line, or the lines after it that are indented
 * more than the def's line. Where the text ends inside the block, it is open unless the change or a comment ends
 * there (the lines after are not the change's).
 */
function pythonBody(r, def, colon) {
  const { toks, text } = r;
  const k = skip(r, colon + 1);
  const base = r.parent[colon];
  if (k < toks.length && toks[k].k !== "nl") {
    let e = k;
    while (e < toks.length && toks[e].k !== "nl") e = toks[e].k === "open" && r.match[e] > 0 ? r.match[e] + 1 : e + 1;
    return bodyStatements(r, k, e, base, 0);
  }
  if (k >= toks.length) return { open: true };
  // The def's line, and the lines after its "nl" tokens.
  const lineStart = text.lastIndexOf(LINE_END, toks[def].s - 1) + 1;
  const indent = /^[ \t]*/.exec(text.slice(lineStart, lineStart + 400))[0].length;
  for (let j = k; j < toks.length; j++) {
    if (toks[j].k !== "nl") continue;
    const nl = nextBreak(text, toks[j].e);
    const line = text.slice(toks[j].e, nl < 0 ? text.length : nl);
    if (!line.trim() || line.trim().startsWith("#")) continue;
    if (/^[ \t]*/.exec(line)[0].length <= indent) return bodyStatements(r, k, j, base, 0);
  }
  return r.end === "line" || r.end === "more" ? { open: true } : bodyStatements(r, k, toks.length, base, 0);
}

/** Whether the group that opens at `open` is a list pattern, not a subscript or a call: [a, b] = ..., list($a, $b) = ..., (a, b) = .... */
function listPattern(r, open) {
  const b = r.toks[back(r, open)];
  if (!b) return true;
  if (r.toks[open].v === "(") return isWord(b, "list") || !(b.k === "word" || b.k === "close" || b.k === "str");
  return !(b.k === "close" || b.k === "str" || (b.k === "word" && !DECLARING.has(b.v) && !isWord(b, "return")));
}

/** A setter by its name: its arguments are the password's value. */
const SETTER_NAME = /^(?:set|with)_?\w{0,40}pass(?:word|wd)$/i;
/** Calls handed a password's name and then its value. */
const SETTER_CALLS = new Set(["set", "append", "setattr", "setItem", "setdefault", "setenv", "put", "ini_set", "setOption", "setConfig", "setParameter", "setHeader", "setValue", "setAttribute", "defineProperty"]);

/** Whether a group around token `i` is still open at the text's end. */
function openAround(r, i) {
  for (let p = r.parent[i]; p >= 0; p = r.parent[p]) if (r.match[p] < 0) return true;
  return false;
}

/** What a value read to the text's end says, by what the end means: true (it counts), false, or "more". */
function verdictAtEnd(r, open, at) {
  if (r.end === "more") return "more";
  if (open === UNENDED) {
    // Its statement may go on past the line: in PHP and JavaScript, or in Python inside an open bracket.
    return r.end === "line" && (r.L.statements || openAround(r, at));
  }
  if (open === EMPTY) return r.end !== "comment";
  return true;
}

// ----- A string's own text ----------------------------------------------------------------------

const VALUE_DELIMITER = /[\s;&,)\]}|<>\\]/;

/**
 * Where names in a string's text are given a value, as indexes into its flattened text: name=value,
 * name = value, and a quoted key's "name": value.
 */
function namesInText(flat) {
  const starts = [];
  // A to Z alone, so each place in `lower` is the same in `flat` (U+0130 lowercases to two characters).
  const lower = asciiLower(flat);
  let from = 0;
  for (let at = lower.indexOf("pass"); at >= 0; at = lower.indexOf("pass", at + 4)) {
    const length = lower.startsWith("word", at + 4) ? 8 : lower.startsWith("wd", at + 4) ? 6 : 0;
    if (!length || /\w/.test(flat[at + length] ?? "")) continue;
    let s = at;
    while (s > from && /[\w-]/.test(flat[s - 1])) s--;
    from = at + length;
    if (/\bcase[ \t]+$/.test(flat.slice(Math.max(0, s - 30), s))) continue;
    let k = at + length;
    const quote = flat[s - 1];
    if ((quote === '"' || quote === "'") && flat[k] === quote) {
      k++;
      while (flat[k] === " " || flat[k] === "\t") k++;
      if (flat[k] !== ":" || flat[k + 1] === ":") continue;
    } else {
      while (flat[k] === " " || flat[k] === "\t") k++;
      if (flat[k] !== "=" || flat[k + 1] === "=" || flat[k + 1] === ">") continue;
    }
    k++;
    while (flat[k] === " " || flat[k] === "\t") k++;
    starts.push(k);
  }
  return starts;
}

/**
 * A value in a string's text from `flat[k]`, unit by unit: a reference, a segment quoted inside, and
 * any other character, which counts, until a delimiter. { done: true, lit } when it ended, or
 * { done: false, quote } when the text ran out first (a quote inside still open, or null). `mask`: the characters an
 * escape spells (escapedMask), which no placeholder holds ('pw=\x25s' counts).
 */
function readInText(flat, k, quote = null, fresh = true, mask = null) {
  const start = k;
  if (fresh) {
    // A placeholder, maybe quoted, is the whole value.
    let word = /^(?:\(\w{1,40}\)|[^\s;&,)\]|\u0000\u0001]){0,300}/.exec(flat.slice(k, k + 400))[0];
    if (!word.includes("{")) word = word.replace(/\}+$/, "");
    const quoted = /^(\\?['"])([\s\S]*)\1$/.exec(word);
    if (!spelledBy(mask, k, k + word.length) && isPlaceholder(quoted ? quoted[2] : word, true)) return { done: true, lit: false };
    const angle = /^<[^<>\u0000\u0001]{0,200}>/.exec(flat.slice(k, k + 210));
    if (angle && !spelledBy(mask, k, k + angle[0].length)) return { done: true, lit: false };
  }
  for (; k < flat.length; k++) {
    const c = flat[k];
    if (c === "\u0000") continue;
    if (c === "\u0001") return { done: true, lit: true };
    if (quote) {
      if (c !== quote) return { done: true, lit: true };
      quote = null;
      continue;
    }
    if (VALUE_DELIMITER.test(c)) return { done: true, lit: false };
    if (c !== "'" && c !== '"' && c !== "`") return { done: true, lit: true };
    // A quote right after the value, before a delimiter, closes a string around it ('echo pw="$PW"').
    if (k > start && /[\w$\])}'"\u0000]/.test(flat[k - 1]) && k + 1 < flat.length && VALUE_DELIMITER.test(flat[k + 1])) return { done: true, lit: false };
    quote = c;
  }
  return { done: false, quote };
}

/** The names in string token `i`'s text and their values, read on into what the code joins to the string. */
function stringReading(r, i) {
  const t = r.toks[i];
  if (!mentions(t.body)) return false;
  const parts = partsOf(t);
  const flat = flatten(parts);
  const mask = escapedMask(parts);
  for (const k of namesInText(flat)) {
    const value = readInText(flat, k, null, true, mask);
    if (value.done) {
      if (value.lit) return true;
      continue;
    }
    if (!t.closed) return verdictAtEnd(r, OPEN, i);
    const joined = readJoined(r, i, value.quote);
    if (joined !== false) return joined;
  }
  return false;
}

/** A string value that runs past its string's close, on through what the code joins to it ("password=" . $pw . " port=5432"). */
function readJoined(r, i, quote) {
  const { toks, L } = r;
  for (let j = i + 1; ; ) {
    let k = skip(r, j, r.end !== "line");
    if (k >= toks.length) return verdictAtEnd(r, quote ? OPEN : UNENDED, i);
    // Python joins adjacent strings without an operator.
    if (!(L.id === "py" && toks[k].k === "str")) {
      if (!isOp(toks[k], L.join)) return quote !== null;
      k = skip(r, k + 1, true);
      if (k >= toks.length) return verdictAtEnd(r, OPEN, i);
    }
    const o = toks[k];
    if (o.k === "str") {
      if (!o.closed) return verdictAtEnd(r, OPEN, i);
      const value = readInText(flatten(partsOf(o)), 0, quote, false);
      if (value.done) return value.lit;
      quote = value.quote;
      j = k + 1;
      continue;
    }
    const operand = readOperand(r, k, 1, {});
    if (operand.lit || operand.text) return true;
    if (operand.open) return verdictAtEnd(r, OPEN, i);
    j = operand.end;
  }
}

// ----- Comments ---------------------------------------------------------------------------------

/** A comment's text, read as the file's code; its end ends a value. In a window the line comments right after it are its text too. */
function commentReading(r, i) {
  const t = r.toks[i];
  if (!mentions(firstLine(t.body))) return false;
  if (r.depth >= READ_DEPTH) return true;
  let body = t.body;
  // Whether the comment runs to the text's end, where the next line may go on with it.
  let toEnd = t.e >= r.text.length;
  if (t.line && r.end !== "line") {
    const marker = r.text.slice(t.s, t.s + (r.text[t.s] === "#" ? 1 : 2));
    for (let k = i + 1; r.toks[k]?.k === "nl"; ) {
      const m = r.toks[k + 1]?.k === "ws" ? k + 2 : k + 1;
      const next = r.toks[m];
      if (!next || next.k !== "comment" || !next.line || !r.text.startsWith(marker, next.s)) break;
      body += `${LINE_END}${next.body}`;
      toEnd = next.e >= r.text.length;
      k = m + 1;
    }
  }
  return textAsCode(body, r.L, r.depth + 1, toEnd && r.end === "more" ? "more" : "comment");
}

/** An apostrophe between two letters (don't, it's): prose's, which PHP's tokens read as a quote's opening. */
const PROSE_APOSTROPHE = /(?<=\p{L})'(?=\p{L})/gu;

/**
 * A comment's or a string's text read as the file's code (commentReading, continuedReading): in a PHP file by PHP's own
 * tokens of the text alone, after "<?php " (phpReader), as commented-out PHP is written. The text is prose as often as
 * code, so in PHP it is read a second time with each prose apostrophe read as a letter (PROSE_APOSTROPHE), and counts
 * where either reading does: "// don't keep $password = '...'" gives the password its literal.
 */
function textAsCode(text, L, depth, end) {
  const verdict = readCode(text, L, depth, end);
  if (verdict === true || L.id !== "php") return verdict;
  const prose = text.replace(PROSE_APOSTROPHE, "x");
  if (prose === text) return verdict;
  const read = readCode(prose, L, depth, end);
  return read === true ? true : verdict === "more" || read === "more" ? "more" : false;
}

/**
 * The inline HTML of PHP reader `r`'s token `i` on the text's first line, outside the code of a <script> element (whose
 * JavaScript is read as JavaScript: phpBlock's js): text that may run as a script's code all the same. PHP's output may
 * print a <script> tag around it (echo '<script>' in data, which phpHtml reads as text), a loop or a jump may print it
 * again after a piece whose HTML state is not known, a function's body is printed where the function is called, and
 * output PHP buffers is printed where PHP says. So it counts where a password's name stands before "=", "=>" or ":"
 * (PASSWORD_SHAPE), and where it gives a password a literal read as the file's code, as a comment's text is (textAsCode:
 * function getPassword() { return '...'; }), or as JavaScript, the language a page's script runs (a method, an
 * object's getter; read from the text's own start). Where the token ends at the text's end, its end ends a value (or
 * the next line is asked for). Where a piece of PHP follows it, what that piece prints is never read and may finish
 * what the token leaves open (getPassword() <?php ?> { return '...'; }, a method's parameters, a declared name before
 * its "="): what either reading leaves open there, or would read on into the next line for, counts.
 */
function htmlReading(r, i) {
  const t = r.toks[i];
  const own = t.html ?? r.text.slice(t.s, t.e);
  const text = own.slice(0, Math.max(0, r.firstEnd - t.s));
  if (!mentions(text)) return false;
  if (PASSWORD_SHAPE.test(text) || r.depth >= READ_DEPTH) return true;
  // Inline HTML ends where the text does or where a piece of PHP opens.
  const piece = t.e < r.text.length;
  const end = piece || r.end === "more" ? "more" : "comment";
  const php = textAsCode(own, r.L, r.depth + 1, end);
  if (php === true || (piece && php === "more")) return true;
  const js = readCode(own, LANGUAGES.js, r.depth + 1, end);
  if (js === true || (piece && js === "more")) return true;
  return js === "more" || php === "more" ? "more" : false;
}

/**
 * The text that a string a line above opens holds on the text's first line (token `i`, which holds the reader's lead:
 * leadOf), read as the file's code, as a comment's text is. A string over lines often holds code (a heredoc's script,
 * a template's markup, a Python string's config), so what such a line gives a password counts; what follows the
 * string's close on the line is read as code after it. The string's close ends its values; where the text ends
 * inside it, the text's end does.
 */
function continuedReading(r, i) {
  const t = r.toks[i];
  const close = !t.closed ? 0 : t.heredoc ? /\w*$/.exec(r.text.slice(0, t.e))[0].length : t.triple ? 3 : 1;
  const text = r.text.slice(0, t.e - close);
  if (!mentions(firstLine(text))) return false;
  if (r.depth >= READ_DEPTH) return true;
  return textAsCode(text, r.L, r.depth + 1, !t.closed && t.e >= r.text.length ? r.end : "comment");
}

// ----- An environment name given a shell word, in any file --------------------------------------

/**
 * PGPASSWORD=<word> after export, after a comment mark, before a command or alone at its line's end,
 * on the text's first line; not after "$", ".", ">", ":", "-", or const, let, var, final or static.
 */
function envAssignment(text, inString = () => false) {
  const line = firstLine(text);
  const name = /\b[A-Z0-9_]*PASS(?:WORD|WD)=/g;
  for (let m = name.exec(line); m; m = name.exec(line)) {
    if (inString(m.index)) continue;
    const before = line.slice(Math.max(0, m.index - 30), m.index);
    if (/[$.>:-]$/.test(before) || /\b(?:const|let|var|final|static)[ \t]{1,20}$/.test(before)) continue;
    const word = shellWord(line, m.index + m[0].length);
    if (!word.lit) continue;
    // What follows the word to its line's end.
    const rest = line.slice(word.end);
    if (/\bexport[ \t]{1,20}$/.test(before) || /(?:\/\/|#|\/\*|^[ \t]*\*)[ \t]{0,20}$/.test(before) || /^[ \t]+[\w./$"'-]/.test(rest) || /^[ \t]*$/.test(rest)) return true;
  }
  return false;
}

/**
 * The shell word at `k`: { lit, end }. Its segments: $X, ${...} (a shell parameter), ${{ ... }},
 * $(...) (unless echo or printf), "..." (references only), '...' (an environment name in capitals or
 * ${...} only), and any other character, which counts. A quote right after a segment, before a space,
 * closes a string around the word. Wholly a placeholder, an example or a constant, it is none.
 */
function shellWord(line, k) {
  let lit = false;
  let j = k;
  const n = line.length;
  while (j < n && !/[\s;&|)]/.test(line[j])) {
    const c = line[j];
    if (c === "$") {
      if (line.startsWith("${{", j)) {
        const close = line.indexOf("}}", j + 3);
        if (close > 0) {
          if (!expressionIsReference(line.slice(j + 3, close), "js", 1)) lit = true;
          j = close + 2;
          continue;
        }
      }
      const close = line[j + 1] === "{" || line[j + 1] === "(" ? closeOf(line, j + 1) : -1;
      if (close > 0) {
        const inner = line.slice(j + 2, close - 1);
        if (line[j + 1] === "{" ? !bracedReference(inner, "js", 1) : !commandReads(inner)) lit = true;
        j = close;
        continue;
      }
      const name = /^\$(?:[A-Za-z_]\w*|[0-9@*#?!$])/.exec(line.slice(j, j + 200));
      if (name) {
        j += name[0].length;
        continue;
      }
    } else if (c === '"' || c === "'") {
      if (j > k && /[\w$}]/.test(line[j - 1]) && /^(?:[\s;&|)]|$)/.test(line.slice(j + 1, j + 2))) break;
      let e = j + 1;
      while (e < n && line[e] !== c) e += line[e] === "\\" && c === '"' ? 2 : 1;
      const value = partsValue(stringParts(line.slice(j + 1, Math.min(e, n)), c === '"' ? "shell" : "literal", c, 1));
      if (value.bad || value.text || e >= n) lit = true;
      j = Math.min(e + 1, n);
      continue;
    }
    lit = true;
    j++;
  }
  const word = line.slice(k, j);
  if (!word || isPlaceholder(word) || TEXT_CONSTANT.test(word)) return { lit: false, end: j };
  return { lit, end: j };
}

// ----- A text file: shell, environment, YAML and config -----------------------------------------

/**
 * Whether position `k` stands strictly inside one of `spans` ({ s, e }: in order, none overlapping, as the lexer and
 * readText's quotes give them): only the last span that starts before `k` can hold it, found by a binary search, so
 * asking once per name or per assignment stays linear in a line with many strings.
 */
function within(spans, k) {
  let lo = 0;
  let hi = spans.length;
  while (lo < hi) {
    const mid = (lo + hi) >> 1;
    if (spans[mid].s < k) lo = mid + 1;
    else hi = mid;
  }
  return lo > 0 && k < spans[lo - 1].e;
}

/**
 * A text file's line: an unquoted value is text. A word after "=" (PGPASSWORD=... psql), the rest of
 * the line after " = " (an INI file) or after a key's ":" (YAML), each segment by segment; a block
 * scalar's lines; a name inside a quoted string, or inside $(...), read as a string's text is. In a
 * YAML file (`yaml`), a key given nothing on its line takes the more-indented lines after it (yamlNext).
 */
function readText(text, end, depth = 0, yaml = false) {
  const line = firstLine(text);
  if (!mentions(line)) return false;
  return readTextLine(text, line, end, depth, yaml);
}

/** readText's reading of `text`, whose first line `line` it reads whole. */
function readTextLine(text, line, end, depth, yaml) {
  // Shell quoting: $(...) holds its own quotes, read on their own; quoted segments; the rest is unquoted.
  let work = line;
  for (let j = work.indexOf("$("); j >= 0; j = work.indexOf("$(", j + 2)) {
    const close = closeOf(work, j + 1);
    const stop = close < 0 ? work.length : close;
    if (depth < READ_DEPTH && readText(work.slice(j + 2, close < 0 ? stop : stop - 1), "change", depth + 1) === true) return true;
    // A file's contents (cat, a redirect) are a reference; any other command's output is literal, and so is one left open.
    const filler = close >= 0 && commandReads(work.slice(j + 2, stop - 1)) ? "$" : "x";
    work = work.slice(0, j) + filler + "_".repeat(stop - j - 1) + work.slice(stop);
  }
  // Whether a YAML key given nothing waits for the lines after it (a quoted key's or a plain one's).
  let more = false;
  const quoted = [];
  for (let j = 0, open = null; j <= work.length; j++) {
    const c = work[j];
    if (open) {
      if (c === undefined) quoted.push({ s: open.s, e: work.length, q: open.q, closed: false });
      else if (c === "\\" && open.q === '"') j++;
      else if (c === open.q) {
        quoted.push({ s: open.s, e: j + 1, q: open.q, closed: true });
        open = null;
      }
    } else if ((c === '"' || c === "'") && !(c === "'" && /[A-Za-z]/.test(work[j - 1] ?? "") && /[A-Za-z]/.test(work[j + 1] ?? ""))) {
      open = { s: j, q: c };
    }
  }
  for (const q of quoted) {
    // A quoted key (YAML, JSON): its value is the rest of the line.
    if (q.closed && /^[\w-]{0,200}pass(?:word|wd)$/i.test(decodeName(work.slice(q.s + 1, q.e - 1)))) {
      const colon = /^[ \t]*:(?!:)[ \t]*(.*)$/.exec(work.slice(q.e));
      const rest = colon ? colon[1].replace(/(?:^|[ \t]+)#.*$/, "").replace(/,[ \t]*$/, "").trim() : null;
      if (rest !== null && textScalar(rest)) return true;
      if (rest === "" && yaml) {
        const verdict = yamlNext(text, end);
        if (verdict === true) return true;
        if (verdict === "more") more = true;
      }
    }
    const parts = stringParts(work.slice(q.s + 1, q.closed ? q.e - 1 : q.e), q.q === '"' ? "shell" : "literal", q.q, 1);
    const flat = flatten(parts);
    const mask = escapedMask(parts);
    for (const k of namesInText(flat)) {
      const value = readInText(flat, k, null, true, mask);
      if (value.done ? value.lit : value.quote !== null || !q.closed || shellWord(work, q.e).lit) return true;
    }
  }
  if (envAssignment(work, (k) => within(quoted, k))) return true;
  const name = /[\w-]*pass(?:word|wd)(?!\w)/gi;
  for (let m = name.exec(work); m; m = name.exec(work)) {
    if (within(quoted, m.index) || /\w/.test(work[m.index - 1] ?? "")) continue;
    const k = m.index + m[0].length;
    const spaced = /^[ \t]*/.exec(work.slice(k, k + 200))[0].length;
    const after = work[k + spaced];
    if (after === "=" && work[k + spaced + 1] !== "=") {
      if (!spaced) {
        if (shellWord(work, k + 1).lit) return true;
        continue;
      }
      // An INI or TOML value: the rest of the line, to a comment or a statement's ";".
      if (textScalar(work.slice(k + spaced + 1).replace(/(?:[ \t]+[#;]|;[ \t]*(?:\/\/|#|$)).*$/, "").trim())) return true;
    } else if (after === ":" && work[k + spaced + 1] !== ":" && /^[ \t]*(?:-[ \t]+)?$/.test(work.slice(0, m.index).replace(/^.*[{,][ \t]*/, ""))) {
      const rest = work.slice(k + spaced + 1).replace(/(?:^|[ \t]+)#.*$/, "").trim();
      if (rest === "" && yaml) {
        const verdict = yamlNext(text, end);
        if (verdict === true) return true;
        if (verdict === "more") more = true;
        continue;
      }
      if (!/^[|>][-+0-9]*$/.test(rest)) {
        if (textScalar(rest)) return true;
        continue;
      }
      // A block scalar: its more-indented lines are the value.
      if (end === "line") return true;
      const block = blockLines(text);
      if (block === null) {
        if (end === "more") more = true;
        else return true;
      } else if (block.some((b) => textScalar(b.trim()))) return true;
    }
  }
  return more ? "more" : false;
}

/**
 * A YAML key given nothing on its line: true, false or "more". Its value is the more-indented lines after it:
 * none is null; a plain scalar's lines or a list's scalar items are the value, read as text; a nested mapping
 * (or a list of them) has keys of its own, each read on its own line. Read alone, or where the text ends
 * before a line shows which, it counts.
 */
function yamlNext(text, end) {
  if (end === "line") return true;
  const block = blockLines(text);
  for (const b of block ?? text.split(LINE_END).slice(1)) {
    const item = b.trim();
    if (!item || item.startsWith("#")) continue;
    if (/^(?:-[ \t]+)?(?:[\w.$-]+|"[^"]*"|'[^']*')[ \t]*:(?:[ \t]|$)/.test(item)) return false;
    if (textScalar(item.replace(/^-(?:[ \t]+|$)/, "").replace(/(?:^|[ \t]+)#.*$/, "").trim())) return true;
  }
  if (block !== null) return false;
  return end === "more" ? "more" : true;
}

/** The more-indented lines after the first (a YAML block scalar), or null when the text ends inside it. */
function blockLines(text) {
  const lines = text.split(LINE_END);
  const indent = /^[ \t]*/.exec(lines[0])[0].length;
  for (let i = 1; i < lines.length; i++) {
    if (lines[i].trim() && /^[ \t]*/.exec(lines[i])[0].length <= indent) return lines.slice(1, i);
  }
  return null;
}

/** A text value (a YAML scalar, an INI value): wholly references, a placeholder, an example, a constant or blank is none. */
function textScalar(value) {
  if (!value || TEXT_CONSTANT.test(value) || isPlaceholder(value)) return false;
  if (value[0] === "{" || value[0] === "[") return false;
  if (value[0] === '"' || value[0] === "'") {
    const q = value[0];
    let e = 1;
    while (e < value.length && value[e] !== q) e += value[e] === "\\" && q === '"' ? 2 : 1;
    if (e !== value.length - 1) return true;
    const v = partsValue(stringParts(value.slice(1, -1), q === '"' ? "shell" : "literal", q, 1));
    // A value an escape spells is never exempt: its characters count.
    return v.bad || (v.refs > 0 || v.esc ? v.text.length > 0 : !exemptText(v.text));
  }
  const v = partsValue(stringParts(value, "shell", "", 1));
  if (v.bad) return true;
  // An alias (*name) is a reference; the spaces between two references are literal.
  return v.text.replace(/^\*[\w-]+$/, "").length > 0 && !(v.refs === 0 && !v.esc && exemptText(v.text));
}

const exemptText = (text) => !text.trim() || isPlaceholder(text.trim()) || TEXT_CONSTANT.test(text.trim());

// ----- PHP's tokens -----------------------------------------------------------------------------
//
// A PHP file is read by PHP's own tokens, never by a lexer here (a hand-written PHP lexer misread one form after
// another). PHP 8.2's token_get_all, run by lib/php.mjs through the PHP php-syntax uses,
// decides where every string, comment, heredoc, nowdoc, "<?php", "?>" and piece of inline HTML starts and ends, and
// what each interpolation holds; the readers read PHP's tokens as they read any language's (phpTokens). A block of
// entries is handed to PHP as its lines joined at LF, as the readers join them; one that starts below the file's first
// line, or a line read alone, after "<?php ", as starting in PHP's code (check.mjs hands every line above a run, so
// its blocks start at line 1). A text that a line or a window of lines reads is a slice of its block's tokens
// (phpSlice): a string, a comment or a piece of HTML that runs over lines is one token there, cut at the window's ends.
// Inside inline HTML, a <script> element's code is found by HTML's own rules (phpHtml: an HTML comment, a tag as HTML's
// tokenizer reads it, raw text elements, foreign content, PHP's pieces between), never by PHP's comments, and is read as
// JavaScript where it stands (js: the block with everything else blanked, each piece of PHP in the code an operand), in
// JavaScript's plain subset, each element as a program of its own (scriptLines). Inline HTML outside a script's code is
// text that may run as a script's code all the same (PHP may print a <script> tag around it, a loop or a jump print it
// again): a password's name in it before "=", "=>" or ":" counts, whatever follows, and so does what it gives a
// password read as PHP's code or as JavaScript (htmlReading). A string's text is read as text: a password it gives a value counts
// unless the value is wholly one of PHP's own interpolations (stringReading, phpParts); a string's later line is read
// as code too (continuedReading). A comment's text is read as PHP's code, by PHP's tokens of the
// text alone, as written and with prose's apostrophes read as letters, and counts where either reading does
// (textAsCode). Where the reading stops being known (phpBlock's unknown: a "<?" short_open_tag decides, a script's
// "<!--" and "<script", two script elements on one line, a tag in foreign content HTML reads otherwise than its own, a
// frameset, a raw text element in a <select>, a literal NUL in inline HTML, a piece of PHP anywhere but in HTML's data
// state, one that decides which HTML is printed in data where a <select> or foreign content is open, one in data
// inside an identifier or a string (insidePiece), and a password name read across one), the block leaves the
// plain subset there, and a line there
// names a password where its text with its pieces of PHP taken out does (getPass<?php ?>word: namedOf). Where PHP's
// tokenizer cannot run (no PHP 8.2 and no Docker, or a process that may start none), or its tokens do not cover a
// text, nothing in that text is read: every line of it that names a password counts (phpFailure says why), and no
// line of it is established as a comment.

/** The tokenizer lib/php.mjs gives (usePhpTokenizer), or null. */
let phpTokenizer = null;
/** Per text PHP has been handed: { raw } (PHP's [name, length] tokens) and its blocks (phpBlock), or { failure }. */
const PHP_TEXTS = new Map();
/** Why PHP's tokenizer could not read a text this process handed it, or null. */
let phpTrouble = null;
/** What a text that does not start at a file's first line is read after: PHP's code. */
const PHP_PREFIX = "<?php ";

/**
 * Give doors.mjs PHP's tokenizer: tokenizer(texts) gives, per text, its tokens as [name, length] (PHP's token name, or
 * the character of a one-character token; the length in the text's characters), or null where they do not cover the
 * text, and throws where PHP cannot run. lib/php.mjs gives its own on import; null takes it away. Returns the one before.
 */
export function usePhpTokenizer(tokenizer) {
  const before = phpTokenizer;
  phpTokenizer = tokenizer;
  phpTrouble = null;
  PHP_TEXTS.clear();
  return before;
}

/** Why PHP's tokenizer could not read a text of a PHP file this process read, or null: every line of it that names a password counted. */
export const phpFailure = () => phpTrouble;

/** The text PHP reads for a block whose first entry has line number `line`: after "<?php " unless it is the file's first line. */
const phpSource = (text, line) => (typeof line === "number" && line <= 1 ? text : PHP_PREFIX + text);

/** PHP's tokens of each of `sources`, asking the tokenizer once for every one not asked before. */
function phpTexts(sources) {
  const missing = [...new Set(sources.filter((source) => !PHP_TEXTS.has(source)))];
  if (missing.length) {
    let answer = null;
    if (!phpTokenizer) phpTrouble ??= "lib/php.mjs, which runs PHP's tokenizer, is not loaded";
    else {
      try {
        answer = phpTokenizer(missing);
      } catch (error) {
        phpTrouble ??= `PHP's tokenizer could not run: ${String(error?.message ?? error).split("\n")[0]}`;
      }
    }
    missing.forEach((source, n) => {
      const raw = answer?.[n] ?? null;
      if (raw === null && answer) phpTrouble ??= "PHP's tokens did not cover a text it was handed";
      PHP_TEXTS.set(source, raw === null ? { failure: phpTrouble } : { raw });
    });
  }
  return sources.map((source) => PHP_TEXTS.get(source));
}

/**
 * Hand PHP every block of the PHP files' entries at once, so that one request reads a change (check.mjs, the pr-body
 * cross-check): `files` holds [path, entries] pairs; a path that is no PHP file is passed over.
 */
export function prefetchPhp(files) {
  const sources = [];
  for (const [path, handed] of files) {
    // PHP_FILES are "*.php" and "*.inc": a path that ends so.
    if (!PHP_FILES.some((glob) => path.endsWith(glob.slice(1)))) continue;
    const lines = ownLines(handed);
    const { top } = entriesOf(lines);
    for (let k = 0; k < lines.length; k++) {
      if (top[k] !== k || typeof lines[k].text !== "string") continue;
      let end = k;
      while (followsOn(lines, end)) end++;
      sources.push(phpSource(lines.slice(k, end + 1).map((entry) => entry.text).join(LINE_END), lines[k].line));
    }
  }
  phpTexts(sources);
}

/** A token list read as a lexer's are: no "/" left unsure (only JavaScript's may be). */
const tokenList = (toks) => Object.assign(toks, { unsure: -1, far: -1 });

/**
 * A block's text as PHP reads it ({ toks, js, html, scripts, unknown, failure }): `toks`, PHP's tokens as the readers
 * read tokens (phpTokens), over the text; `js`, the text with all but its <script> elements' code blanked (phpHtml);
 * `html`, the text with all but its inline HTML outside script code blanked; `scripts`, each element's code { s, e };
 * `unknown`, where the reading stops being known, or -1: where HTML's does (phpHtml), and at a "<?" in inline HTML,
 * which opens no "<?php" or "<?=" here but opens PHP's code where short_open_tag is on (lib/php.mjs reads with it off,
 * as php.ini-production sets it). `failure`: why PHP did not read it, and no tokens. Read once per text.
 */
function phpBlock(text, line) {
  const source = phpSource(text, line);
  const [read] = phpTexts([source]);
  if (read.failure !== undefined) return { toks: tokenList([]), failure: read.failure ?? "PHP's tokenizer could not run" };
  // One source is two texts: "<?php $x;" read from a file's first line, and "$x;" read below it, after "<?php ".
  const base = source.length - text.length;
  read.blocks ??= new Map();
  if (!read.blocks.has(base)) {
    const list = [];
    let at = 0;
    for (const [name, length] of read.raw) {
      list.push({ name, s: at, e: at + length });
      at += length;
    }
    const all = tokenList([]);
    phpTokens(source, list, 0, list.length, 0, all);
    const toks = tokenList(base ? all.filter((t) => t.e > base).map((t) => shifted(t, base)) : all);
    for (const t of toks) if (t.php) t.php = { ...t.php, base };
    const html = phpHtml(text, toks);
    const short = toks.find((t) => t.k === "html" && text.slice(t.s, t.e).includes("<?"));
    const tag = short ? text.indexOf("<?", short.s) : -1;
    let unknown = tag >= 0 && (html.unknown < 0 || tag < html.unknown) ? tag : html.unknown;
    // Where short_open_tag decides what is PHP, a name may stand across a piece PHP's tokens read as inline HTML: the
    // text is read for one as where the tokenizer did not run (textNames), each "<?" read both as a piece's start and as
    // text; the state is not known from such a name's start, as from one across a piece PHP's tokens show (phpHtml).
    const names = short ? [...html.names, ...textNames(text, base > 0)] : html.names;
    for (const [s] of names) if (unknown < 0 || s < unknown) unknown = s;
    read.blocks.set(base, { toks, ...html, names, unknown, failure: null });
  }
  return read.blocks.get(base);
}

/** Token `t` with its places moved `by` characters back (s, e, and a string's or a comment's body: bs, be). */
function shifted(t, by) {
  const out = { ...t, s: t.s - by, e: t.e - by };
  if (t.bs !== undefined) {
    out.bs = t.bs - by;
    out.be = t.be - by;
  }
  return out;
}

/** PHP's names of a cast: (int), ( string ) and the rest are one token, read as "(", the type and ")". */
const PHP_CAST = /^T_(?:INT|DOUBLE|STRING|ARRAY|OBJECT|BOOL|UNSET|BINARY)_CAST$/;
/** The keys PHP's simple interpolation $a[key] takes: an unquoted name, a number and a $variable. */
const PHP_SIMPLE_KEYS = new Set(["T_STRING", "T_NUM_STRING", "T_VARIABLE"]);
/** The tokens that open an interpolation's code in a PHP string: "{$" and "${". */
const PHP_INTERPOLATION = new Set(["T_CURLY_OPEN", "T_DOLLAR_OPEN_CURLY_BRACES"]);
/**
 * The token that closes the string PHP's token `name` opens, or null where it opens none: '"' for '"' and for a binary
 * string's opener, which PHP gives as the one token b" or B" (a binary string with no interpolation is one
 * T_CONSTANT_ENCAPSED_STRING), "`" for "`", and a heredoc's end for its start (a binary heredoc's start too).
 */
function phpStringCloser(name) {
  if (name === '"' || name === 'b"' || name === 'B"') return '"';
  if (name === "`") return "`";
  if (name === "T_START_HEREDOC") return "T_END_HEREDOC";
  return null;
}

/** Whitespace from `s` to `e` of `src` as the readers' tokens: an LF or a CR LF is a line break, a bare CR no whitespace. */
function spaceTokens(src, s, e, out) {
  for (let i = s; i < e; ) {
    const brk = breakAt(src, i);
    if (brk) {
      out.push({ k: "nl", s: i, e: i + brk });
      i += brk;
    } else if (src[i] === "\r") {
      out.push({ k: "other", s: i, e: i + 1, v: "\r" });
      i++;
    } else {
      let j = i;
      while (j < e && src[j] !== "\n" && src[j] !== "\r") j++;
      out.push({ k: "ws", s: i, e: j });
      i = j;
    }
  }
}

/** Where the string that PHP's token `i` of `list` opens ('"', b" or B", "`" or a heredoc's start: phpStringCloser) closes: its closing token, or list.length. */
function phpStringEnd(list, i) {
  const stack = [{ closer: phpStringCloser(list[i].name), depth: 0 }];
  for (let j = i + 1; j < list.length; j++) {
    const top = stack[stack.length - 1];
    const { name } = list[j];
    if (top.depth === 0) {
      if (name === top.closer) {
        stack.pop();
        if (!stack.length) return j;
      } else if (PHP_INTERPOLATION.has(name)) top.depth = 1;
      continue;
    }
    if (name === "{" || PHP_INTERPOLATION.has(name)) top.depth++;
    else if (name === "}") top.depth--;
    else if (phpStringCloser(name)) stack.push({ closer: phpStringCloser(name), depth: 0 });
  }
  return list.length;
}

/** A PHP token that is one character, a name or an operator, as the readers' token. */
function phpPlain(v, s, e) {
  if (v === "(" || v === "[" || v === "{") return { k: "open", s, e, v };
  if (v === ")" || v === "]" || v === "}") return { k: "close", s, e, v };
  if (v === ";") return { k: "semi", s, e, v };
  if (v === ",") return { k: "comma", s, e, v };
  if (OPERATORS.includes(v)) return { k: "op", s, e, v };
  if (/^[$\\]?[A-Za-z_\u0080-\uffff][\\\w\u0080-\uffff]*$/.test(v)) return { k: "word", s, e, v };
  return { k: "other", s, e, v };
}

/**
 * PHP's tokens `list[from..to)` of `src` as the readers' tokens, pushed onto `out`: whitespace, line breaks (nl), PHP's
 * open tags (ws) and "?>" (end), inline HTML (html), comments { body, line, closed }, strings { body, reading, fence,
 * closed, heredoc } (a '...' string reads "plain", a "..." one without interpolation "dollar", and one with
 * interpolations, a backtick string and a heredoc carry PHP's own tokens of them: phpParts; a nowdoc reads "plain"),
 * names (word: a variable, a keyword, a name with its namespace), numbers, groups, operators and other characters. A
 * cast is "(", its type and ")". Each string's and comment's body is where it stands (bs, be).
 */
function phpTokens(src, list, from, to, depth, out) {
  for (let i = from; i < to; i++) {
    const { name, s, e } = list[i];
    const v = src.slice(s, e);
    if (name === "T_WHITESPACE") spaceTokens(src, s, e, out);
    else if (name === "T_OPEN_TAG") {
      const tag = /^<\?(?:php)?/i.exec(v)[0].length;
      out.push({ k: "ws", s, e: s + tag });
      spaceTokens(src, s + tag, e, out);
    } else if (name === "T_OPEN_TAG_WITH_ECHO") out.push({ k: "ws", s, e });
    else if (name === "T_CLOSE_TAG") {
      out.push({ k: "end", s, e: s + 2 });
      spaceTokens(src, s + 2, e, out);
    } else if (name === "T_INLINE_HTML") out.push({ k: "html", s, e });
    else if (name === "T_COMMENT" || name === "T_DOC_COMMENT") {
      const line = !v.startsWith("/*");
      const open = v.startsWith("#") ? 1 : 2;
      const closed = line || (v.length >= 4 && v.endsWith("*/"));
      const be = line || !closed ? e : e - 2;
      out.push({ k: "comment", s, e, body: src.slice(s + open, be), bs: s + open, be, line, closed });
    } else if (name === "T_CONSTANT_ENCAPSED_STRING") {
      const q = /^[bB]/.test(v) ? 1 : 0;
      const fence = v[q];
      out.push({ k: "str", s, e, body: v.slice(q + 1, -1), bs: s + q + 1, be: e - 1, reading: fence === "'" ? "plain" : "dollar", fence, depth, closed: true, triple: false });
    } else if (phpStringCloser(name)) {
      const close = phpStringEnd(list, i);
      const closed = close < to;
      const heredoc = name === "T_START_HEREDOC";
      const nowdoc = heredoc && v.includes("'");
      const end = closed ? list[close].s : src.length;
      // A heredoc's last line break, before its closing label, is no text of it.
      const be = heredoc && closed ? end - (src[end - 1] === "\n" ? (src[end - 2] === "\r" ? 2 : 1) : 0) : end;
      out.push({
        k: "str",
        s,
        e: closed ? list[close].e : src.length,
        body: src.slice(e, Math.max(e, be)),
        bs: e,
        be: Math.max(e, be),
        reading: nowdoc ? "plain" : "dollar",
        fence: heredoc ? "" : phpStringCloser(name),
        depth,
        closed,
        triple: false,
        ...(heredoc ? { heredoc: true } : {}),
        ...(nowdoc ? {} : { php: { src, list, from: i + 1, to: Math.min(close, to), base: 0, lo: e, hi: Math.max(e, be) } }),
      });
      i = Math.min(close, to - 1);
    } else if (name === "T_ENCAPSED_AND_WHITESPACE" && v.startsWith("'")) {
      // A '...' the text ends inside.
      out.push({ k: "str", s, e, body: v.slice(1), bs: s + 1, be: e, reading: "plain", fence: "'", depth, closed: false, triple: false });
    } else if (PHP_CAST.test(name)) {
      const m = /^\((\s*)([A-Za-z]+)(\s*)\)$/.exec(v);
      if (!m) out.push({ k: "other", s, e, v });
      else {
        out.push({ k: "open", s, e: s + 1, v: "(" });
        if (m[1]) out.push({ k: "ws", s: s + 1, e: s + 1 + m[1].length });
        const w = s + 1 + m[1].length;
        out.push({ k: "word", s: w, e: w + m[2].length, v: m[2] });
        if (m[3]) out.push({ k: "ws", s: w + m[2].length, e: e - 1 });
        out.push({ k: "close", s: e - 1, e, v: ")" });
      }
    } else if (name === "T_ATTRIBUTE") {
      out.push({ k: "other", s, e: s + 1, v: "#" }, { k: "open", s: s + 1, e, v: "[" });
    } else if (name === "T_YIELD_FROM") {
      out.push({ k: "word", s, e, v: "yield" });
    } else if (name === "T_LNUMBER" || name === "T_DNUMBER" || name === "T_NUM_STRING") out.push({ k: "num", s, e, v });
    else out.push(phpPlain(v, s, e));
  }
  return out;
}

/**
 * The parts of a PHP string (stringParts' shape) by PHP's own tokens of it (phpTokens' `php`): its text between
 * interpolations ("dollar": a $(...) or a $1 there is a shell's), each $name with what PHP reads with it ($a[k], $a->b) a reference, ${name} a reference,
 * and each {$...} or ${...} what its code is, as the readers read PHP's tokens of it: a reference, or a literal (bad).
 * Only what stands inside the string's own text where its token was cut (lo, hi: phpSlice) is read; a part cut there
 * is a literal.
 */
function phpParts(tok) {
  const { src, list, from, to, lo, hi } = tok.php;
  const parts = [];
  const lit = (s, e) => {
    const a = Math.max(s, lo);
    const b = Math.min(e, hi);
    if (b > a) parts.push(...stringParts(src.slice(a, b), "dollar", tok.fence, tok.depth));
  };
  for (let j = from; j < to; ) {
    const t = list[j];
    if (t.name === "T_ENCAPSED_AND_WHITESPACE") {
      lit(t.s, t.e);
      j++;
      continue;
    }
    let k = j + 1;
    let ref = false;
    const name = (m) => (m < to ? list[m].name : null);
    if (t.name === "T_VARIABLE") {
      // PHP's simple syntax: $a, $a[key] (an unquoted name, a number, -number or a $variable, then "]"), $a->b, $a?->b.
      // A "[" that holds anything else does not parse: what PHP's tokens show there is a literal.
      ref = true;
      if (name(k) === "[") {
        const key = name(k + 1) === "-" && name(k + 2) === "T_NUM_STRING" ? k + 3 : PHP_SIMPLE_KEYS.has(name(k + 1)) ? k + 2 : -1;
        if (key > 0 && name(key) === "]") k = key + 1;
        else {
          ref = false;
          k += 1;
        }
      } else if ((name(k) === "T_OBJECT_OPERATOR" || name(k) === "T_NULLSAFE_OBJECT_OPERATOR") && name(k + 1) === "T_STRING") k += 2;
    } else if (PHP_INTERPOLATION.has(t.name)) {
      let d = 1;
      for (; k < to && d > 0; k++) {
        const n = list[k].name;
        if (n === "{" || PHP_INTERPOLATION.has(n)) d++;
        else if (n === "}") d--;
        else if (phpStringCloser(n)) k = phpStringEnd(list, k);
      }
      const inner = [j + 1, k - 1];
      // ${...} is read as every string's is too (shellBraced: ${X:+...} adds a literal), then as PHP reads its code.
      const shell = t.name === "T_DOLLAR_OPEN_CURLY_BRACES" && d === 0 ? shellBraced(src.slice(t.e, list[k - 1].s)) : null;
      ref = d === 0 && shell !== false && (shell === true || phpExpression(src, list, inner[0], inner[1], tok.depth + 1));
    }
    const s = t.s;
    const e = list[Math.min(k, to) - 1]?.e ?? t.e;
    if (e <= lo || s >= hi) {
      j = Math.max(k, j + 1);
      continue;
    }
    parts.push(ref && s >= lo && e <= hi ? { ref: true } : { bad: true });
    j = Math.max(k, j + 1);
  }
  return parts;
}

/** Whether PHP's tokens `list[a..b)` of `src` (an interpolation's code) are wholly one value that holds no literal. */
function phpExpression(src, list, a, b, depth) {
  if (depth > READ_DEPTH || a >= b) return false;
  const s = list[a].s;
  const toks = tokenList(phpTokens(src, list, a, b, depth, []).map((t) => shifted(t, s)));
  const r = readerOver(src.slice(s, list[b - 1].e), toks, LANGUAGES.php, depth, "change");
  const start = skip(r, 0, true);
  if (start >= r.toks.length) return false;
  const value = readValue(r, start, 0, { group: true });
  return !value.lit && (!value.open || value.open === UNENDED) && skip(r, value.end, true) >= r.toks.length;
}

/**
 * A reader over characters [from, to) of PHP block reader `b`'s text, its tokens the block's: a token the window cuts
 * stays one, cut there (one cut at the start holds the window's lead: lead, as lexAfter's first token does; one cut at
 * the end is not closed), with the body of its text inside the window, and inline HTML its outside-script text (html).
 */
function phpSlice(b, from, to, depth, end, before = null) {
  const html = b.html ?? null;
  const out = tokenList([]);
  for (let t = tokenAfter(b, from); t < b.toks.length && b.toks[t].s < to; t++) {
    const tok = b.toks[t];
    const cut = { ...shifted(tok, from) };
    delete cut.cache;
    delete cut.phpParts;
    if (tok.k === "str") cut.depth = depth;
    if (tok.s < from || tok.e > to) {
      cut.s = Math.max(tok.s, from) - from;
      cut.e = Math.min(tok.e, to) - from;
      if (tok.s < from) cut.lead = true;
      if (tok.e > to) cut.closed = false;
      if (tok.bs !== undefined) {
        cut.bs = Math.min(Math.max(tok.bs, from), to) - from;
        cut.be = Math.max(Math.min(tok.be, to), from) - from;
        cut.body = b.text.slice(cut.bs + from, Math.max(cut.bs, cut.be) + from);
      }
      if (tok.php) cut.php = { ...tok.php, lo: Math.max(tok.php.lo, from + tok.php.base), hi: Math.min(tok.php.hi, to + tok.php.base) };
    }
    if (tok.k === "html" && html !== null) cut.html = html.slice(Math.max(tok.s, from), Math.min(tok.e, to));
    out.push(cut);
  }
  const r = readerOver(b.text.slice(from, to), out, LANGUAGES.php, depth, end, before);
  return r;
}

/**
 * The reader of PHP text `text` (readCode's): a window of its block's tokens where the lines above it are known
 * (contextAt), else PHP's tokens of the text alone, read as starting in PHP's code; null where PHP did not read it.
 */
function phpReader(text, depth, end, before) {
  if (before) {
    const layout = layoutOf(before.lines, before.index, LANGUAGES.php);
    if (layout.php.failure) return null;
    const from = layout.offset[before.index - layout.start];
    if (layout.b.text.startsWith(text, from)) return phpSlice(layout.b, from, from + text.length, depth, end, before);
  }
  const block = phpBlock(text, null);
  if (block.failure) return null;
  const b = readerOver(text, block.toks, LANGUAGES.php, depth, end);
  b.html = block.html;
  return phpSlice(b, 0, text.length, depth, end, before);
}

/** HTML's raw text elements, whose text holds no tag: a <script> in one opens none. A <plaintext> runs to the end. */
const HTML_RAW = new Set(["style", "textarea", "title", "xmp", "iframe", "noembed", "noframes", "noscript", "plaintext"]);

/**
 * The tags inside foreign content (an <svg> or a <math> element) where HTML's reading of it stops being the reading of
 * HTML's own elements that phpHtml knows: a script (an SVG script's code is read with its character references and
 * CDATA), a raw text element's name (raw text in HTML, an element like any other in SVG and MathML, which a "/>" may
 * close), an integration point (SVG's foreignObject, desc and title, MathML's mi, mo, mn, ms, mtext and
 * annotation-xml, whose content HTML reads as its own), and each tag that breaks out of foreign content into HTML.
 */
const FOREIGN_UNKNOWN = new Set([
  "script",
  ...HTML_RAW,
  "foreignobject",
  "desc",
  "mi",
  "mo",
  "mn",
  "ms",
  "mtext",
  "annotation-xml",
  ..."b big blockquote body br center code dd div dl dt em embed font h1 h2 h3 h4 h5 h6 head hr i img li listing menu meta nobr ol p pre ruby s small span strike strong sub sup table tt u ul var".split(" "),
]);

/** HTML's whitespace in a tag. */
const HTML_SPACE = /[\t\n\f\r ]/;

/**
 * What stands for a piece of PHP in phpHtml's joined inline HTML. HTML's tokenizer never reads a NUL as itself: it reads
 * a literal one in a tag's name, an attribute, a comment and raw text as U+FFFD, and as no delimiter anywhere. So
 * phpHtml reads a literal NUL as U+FFFD, and PIECE stands for PHP alone.
 */
const PIECE = "\u0000";

/** PHP's words that end a block of its alternative syntax, and those that jump to another place of the HTML around them. */
const PHP_BLOCK_ENDS = new Set(["endif", "endforeach", "endfor", "endwhile", "endswitch", "enddeclare"]);
const PHP_JUMPS = new Set(["break", "continue", "goto"]);
/** PHP's words whose head in "(...)" a ":" after it makes a block of its alternative syntax. */
const PHP_HEADS = new Set(["if", "elseif", "foreach", "for", "while", "switch", "declare"]);
/** A name PHP's goto may jump to: a label's. */
const PHP_LABEL = /^[A-Za-z_\u0080-￿][\w\u0080-￿]*$/;

/**
 * Whether PHP's tokens toks[a, b) (a piece of PHP between two pieces of inline HTML) decide which HTML around them is
 * printed, or from where: a block over pieces of HTML (a condition, a loop, a switch, a function's body) that a "{" the
 * piece leaves open or a "}" it closes opens or ends, or a head of the alternative syntax (if (...):, else:, foreach
 * (...):, ...) or an end word (endif, ...) that opens or ends one; a jump out of the piece's own blocks (break,
 * continue, goto); and a place outside them that a jump lands on, from which printing may start with the HTML before it
 * passed over: a switch's case or default label, a goto's label where a statement starts. A piece whose blocks close
 * in it prints its HTML once and in order: it is none.
 */
function controlsHtml(toks, a, b) {
  let depth = 0;
  let low = 0;
  const next = (t) => {
    while (t < b && (TRIVIA.has(toks[t].k) || toks[t].k === "nl")) t++;
    return t;
  };
  // `before`: the token before the one read, past whitespace and comments (null at the piece's start): a label stands
  // where a statement starts, after a ";", a "{", a "}", a ":" or nothing.
  for (let t = next(a), before = null; t < b; before = toks[t], t = next(t + 1)) {
    const { k, v } = toks[t];
    if (k === "open" && v === "{") depth++;
    else if (k === "close" && v === "}") low = Math.min(low, --depth);
    if (k !== "word") continue;
    const w = v.toLowerCase();
    let after = next(t + 1);
    const colon = toks[after]?.k === "op" && toks[after].v === ":";
    const starts = before === null || before.k === "semi" || before.v === "{" || before.v === "}" || (before.k === "op" && before.v === ":");
    // A jump out of the piece's blocks (a goto may land anywhere), and a label outside them (a match's default is "=>").
    if (PHP_JUMPS.has(w) && (w === "goto" || depth <= 0)) return true;
    if (depth <= 0 && (w === "case" || (w === "default" && (colon || toks[after]?.k === "semi")))) return true;
    if (depth <= 0 && colon && starts && w !== "else" && w !== "default" && PHP_LABEL.test(v)) return true;
    if (PHP_BLOCK_ENDS.has(w)) {
      low = Math.min(low, --depth);
      continue;
    }
    if (w === "else" && colon) {
      low = Math.min(low, depth - 1);
      continue;
    }
    if (!PHP_HEADS.has(w) || toks[after]?.v !== "(") continue;
    // The head's ")" and what follows it: a ":" opens a block of the alternative syntax (elseif's closes one first).
    for (let open = 0; after < b; after++) {
      if (toks[after].k === "open" && toks[after].v === "(") open++;
      else if (toks[after].k === "close" && toks[after].v === ")" && --open === 0) break;
    }
    if (toks[next(after + 1)]?.v !== ":") continue;
    if (w === "elseif") low = Math.min(low, depth - 1);
    else depth++;
  }
  return low < 0 || depth !== 0;
}

/**
 * Where a PHP block's <script> elements' code stands, by HTML's own rules over its inline HTML (PHP's html tokens; a
 * piece of PHP between them is no HTML): { js, html, scripts, unknown } (phpBlock). HTML reads its text as data, a
 * comment from "<!--" to "-->" (or to the end), "<!", "</" without a letter and "<?" to ">", a tag from "<" and a letter
 * to its ">" as HTML's tokenizer reads it (tagAt: its attributes, each value quoted or not, a "=" before an attribute's
 * name its name's, and a "/>" that closes it); after a <script> tag its code runs to "</script", and a raw text
 * element's text (style, textarea, title, ...) to its end tag, a <plaintext>'s to the end. So a "//" or a "/*" in an
 * HTML comment, a tag or a raw text element is HTML's text, never a comment's mark. Names are folded as HTML
 * folds them, A to Z alone (asciiLower), so every place found in the folded text is the text's own, and a literal NUL is
 * read as HTML reads it, as U+FFFD (PIECE), so <style\0> is no style element. Inside an <svg> or a <math> element
 * (foreign content, until the end tag that closes it, which "/>" on its start tag does at once) HTML reads the tags
 * phpHtml knows as it reads HTML's own: an element that a "/>" closes, or that its end tag or an end tag of an element
 * around it closes; a CDATA section, a tag of FOREIGN_UNKNOWN and an end tag of no open element leave HTML's
 * reading unknown. A piece of PHP inside a script's code is an operand there ("_").
 *
 * A piece of PHP prints what the text does not show, and phpHtml never reads what. In HTML's data
 * state it is text. Anywhere else (in a tag: its name, an attribute, a value; in a comment, a bogus comment or a CDATA
 * section; in a raw text element's text or a script's code; and where it starts a tag's name: "<<?php", "</<?php") what
 * it prints may be anything, a "<" too, so it may finish the delimiter the text has begun (<style>x<<?php echo '/';
 * ?>style><script>, <!-- -<?php echo '-'; ?>><script>), end the tag, comment or element and open any other (a script,
 * a comment, a raw text element, a quoted value, foreign content): HTML's state after it is not known, and the block
 * leaves the plain subset at it (htmlCodes). No later place the text shows is certain: only a text that closes every
 * one of those states in every order would bring all the readings back to one, and no page holds one. A <plaintext>'s
 * text is the one exception: HTML never leaves it, so nothing after a piece there is ever more than text. A piece that
 * prints nothing is read as one that prints. A piece that decides which HTML is printed, or from where (controlsHtml: a
 * block over pieces of HTML, a jump, a switch's case or default label, a goto's label), joins the HTML before it to the
 * HTML after another piece: outside data it is not known as any piece there is not, and in data it is, as each stretch
 * between two such pieces starts and ends in data, unless a <select> or foreign content is open there, which changes
 * what HTML reads after it. In a <plaintext>'s text such a piece is not known either: the HTML after it may be printed
 * without the <plaintext> tag (<?php if ($a): ?><plaintext><?php endif; ?><script>). Inline HTML that some printing
 * order or PHP's output may print inside a script's code (the text before such a piece, markup PHP prints in data) is
 * read as JavaScript and as PHP's code: htmlReading's.
 *
 * `scripts`: each element's code, { s, e }, in order. `unknown`: where HTML's reading stops being known, or -1: there in
 * foreign content, at a piece of PHP above, a <frameset> and a raw text element but a textarea inside a <select> (a
 * frameset ignores a script and a raw text element, and a <select> may ignore a raw text element), a script's code that
 * holds "<!--" and then "<script" before its end (HTML may run the script past its "</script>"), an element whose code
 * starts on the line where the one before it ends (the readers read an element's code as its own lines: scriptLines),
 * the text's start where the places found do not hold together, and a piece of PHP inside an identifier or a string
 * (insidePiece: what it prints joins the text on its two sides, so the readings of the inline HTML as code,
 * each from its own token's start, may not be the text's). `names`: each password name the inline HTML spells across
 * a piece of PHP (getPass<?php ?>word), as [start, end) in the text (splitNames), which no line names as written, and
 * each name a piece may complete, where a name's text touches it whatever that text holds (getPass<?= 'word' ?>,
 * get<?= 'Pass' . 'word' ?>, <?= 'getPass' ?>word: edgeNames); the reading is not known from each one's
 * start, whatever stands beside its piece. Where the text starts below a file's first line, it is read as starting in
 * PHP's code (phpSource): that code is its first piece, so a name's text after it stands with it.
 */
function phpHtml(text, toks) {
  // The inline HTML, joined: a piece of PHP between two of its tokens stands as PIECE (at: -1 - the piece's index), a
  // literal NUL as U+FFFD. HTML's reading is not known from a literal NUL on: browsers have read one otherwise than
  // HTML's tokenizer does.
  let joined = "";
  const at = [];
  const pieces = [];
  // The places in `joined` of the pieces that decide which HTML is printed, or from where (controlsHtml), in order.
  const controls = [];
  let unknown = Infinity;
  for (let t = 0; t < toks.length; ) {
    if (toks[t].k === "html") {
      for (let i = toks[t].s; i < toks[t].e; i++) {
        let c = text[i];
        if (c === PIECE) {
          c = "\uFFFD";
          unknown = Math.min(unknown, i);
        }
        joined += c;
        at.push(i);
      }
      t++;
      continue;
    }
    const s = toks[t].s;
    const from = t;
    while (t < toks.length && toks[t].k !== "html") t++;
    pieces.push({ s, e: t < toks.length ? toks[t].s : text.length });
    if (controlsHtml(toks, from, t)) controls.push(at.length);
    joined += PIECE;
    at.push(-pieces.length);
  }
  /** Where the joined HTML's character `i` stands in the text: its own place, or the start of the piece of PHP it stands for. */
  const textAt = (i) => (i >= at.length ? text.length : at[i] >= 0 ? at[i] : pieces[-at[i] - 1].s);
  const kept = htmlCodes(joined, controls);
  for (const place of kept.doubts) unknown = Math.min(unknown, textAt(place));
  const js = text.replace(/[^\n]/g, " ").split("");
  const html = text.replace(/[^\n]/g, " ").split("");
  const keep = (a, b, script) => {
    for (let i = Math.max(0, a); i < Math.min(b, at.length); i++) {
      const p = at[i];
      if (p >= 0) {
        if (script) js[p] = text[p];
        else html[p] = text[p];
      } else if (script) {
        const piece = pieces[-p - 1];
        for (let c = piece.s; c < piece.e; c++) if (text[c] !== "\n") js[c] = c === piece.s ? "_" : " ";
      }
    }
  };
  const scripts = [];
  let from = 0;
  for (const [s, e] of kept.codes) {
    const code = { s: textAt(s), e: textAt(e) };
    const before = scripts[scripts.length - 1];
    if (before && !text.slice(before.e, code.s).includes(LINE_END)) unknown = Math.min(unknown, code.s);
    scripts.push(code);
    keep(from, s, false);
    keep(s, e, true);
    from = e;
  }
  keep(from, at.length, false);
  // Each element's code lies in the text, in order; where the places found do not hold together, nothing is known.
  if (scripts.some((code, k) => !(code.s >= 0 && code.s <= code.e && code.e <= text.length && (k === 0 || scripts[k - 1].e <= code.s)))) unknown = 0;
  const inside = insidePiece(joined);
  if (inside >= 0) unknown = Math.min(unknown, textAt(inside));
  // A name a piece of PHP may complete from one side stands over the piece and the name's text beside it (edgeNames:
  // getPass<?= 'word' ?>()). Where the text starts below a file's first line, its first piece is the PHP code it
  // starts in (phpSource), which may print the start of a name the text after it ends.
  const names = [...splitNames(joined, textAt), ...edgeNames(text, pieces)];
  // A password name the inline HTML spells across a piece of PHP leaves the state unknown from its start, whatever
  // stands beside the piece (get\u{50}<?php ?>assword, an escape's "}" before the piece, which insidePiece reads as
  // an identifier's end too): what the piece prints joins the name, so the readings
  // of the text from its own start on its two sides do not read it.
  for (const [s] of names) unknown = Math.min(unknown, s);
  return { js: js.join(""), html: html.join(""), scripts, unknown: unknown === Infinity ? -1 : unknown, names, pieces };
}

/**
 * A character an identifier holds, in JavaScript and PHP alike (a name, a number's digits, a combining mark, a connector
 * such as "_", ZWNJ and ZWJ, and the backslash of an escape such as \u0077): one on each side of a piece of PHP puts the
 * piece inside an identifier. Read a code point at a time (identifierAt), so a letter past U+FFFF counts. A name's text
 * (NAME_TEXT) holds each of them, as it is built from this list.
 */
const IDENTIFIER_CHAR = /[\p{L}\p{N}\p{ID_Continue}$\\\u200c\u200d]/u;
const HIGH_SURROGATE = /[\ud800-\udbff]/;
const LOW_SURROGATE = /[\udc00-\udfff]/;
/** The code point of `text` that ends at `i`, as a string: a surrogate pair whole, any other UTF-16 unit (a lone surrogate too) alone. */
const codeBefore = (text, i) => (i >= 2 && LOW_SURROGATE.test(text[i - 1]) && HIGH_SURROGATE.test(text[i - 2]) ? text.slice(i - 2, i) : text[i - 1]);
/** The code point of `text` that starts at `i`, as a string, as codeBefore reads one. */
const codeAt = (text, i) => (HIGH_SURROGATE.test(text[i]) && LOW_SURROGATE.test(text[i + 1] ?? "") ? text.slice(i, i + 2) : text[i]);

/** Whether the code point of `joined` that ends at `a` (`end`: its last UTF-16 unit) or starts at `a` is an identifier's (IDENTIFIER_CHAR), a surrogate pair read whole. */
const identifierAt = (joined, a, end) => IDENTIFIER_CHAR.test(end ? codeBefore(joined, a + 1) : codeAt(joined, a));
/** A letter: an apostrophe between two is prose's, no quote. */
const LETTER = /\p{L}/u;
/** A hexadecimal digit, as an escape's code point holds. */
const HEX_DIGIT = /[0-9a-fA-F]/;

/**
 * How JavaScript's identifier escape (\uXXXX or \u{X...}) stands at `joined[a]`, the character before a piece of PHP:
 * "open" where the piece stands inside one ("\u", "\u{", "\u{5", "\u00"), so
 * whatever follows the piece is the escape's, "closed" where one ends there with its "}" ("\u{50}"), so the piece stands
 * after an identifier's character, or null. Read back over at most NAME_WINDOW of the escape's digits.
 */
function escapeBefore(joined, a) {
  const braced = (d) => joined[d] === "{" && joined[d - 1] === "u" && joined[d - 2] === "\\";
  if (joined[a] === "}") {
    let d = a - 1;
    while (d >= 0 && a - d < NAME_WINDOW && HEX_DIGIT.test(joined[d])) d--;
    return d < a - 1 && braced(d) ? "closed" : null;
  }
  let d = a;
  while (d >= 0 && a - d < NAME_WINDOW && HEX_DIGIT.test(joined[d])) d--;
  if (braced(d)) return "open";
  // \u and up to three of its four digits; a "u" that is a digit's place is no escape's.
  return joined[d] === "u" && joined[d - 1] === "\\" && a - d <= 3 ? "open" : null;
}

/**
 * The first piece of PHP (PIECE) in `joined`, phpHtml's inline HTML joined, that stands inside an identifier or a
 * string, or -1. What a piece prints is never read, and the readings of the inline HTML as
 * code read each piece of it from its own start (htmlReading): where a piece stands inside an identifier (a character
 * an identifier holds on each side of it, past the pieces beside it: getPass<?php ?>word; an identifier's escape that
 * ends with its "}" before it, get\u{50}<?php ?>assword; or inside such an escape, \u{<?php ?>50}: escapeBefore) or a
 * string (a quote opened before it on its line and not closed, JavaScript's and PHP's ' and " alike, a backslash
 * escaping the next character, a prose apostrophe between two letters read as a letter, as textAsCode reads one; a
 * backtick's template over lines),
 * what it prints joins the text on its two sides, so the text after it may read otherwise than from its own start: a
 * string's close read as its opening, the rest of a name read as a name of its own. The state there is not known. One
 * reading, in linear time: the quotes' state is kept from piece to piece.
 */
function insidePiece(joined) {
  let quote = null;
  for (let i = 0; i < joined.length; i++) {
    const c = joined[i];
    if (c === LINE_END) {
      // A ' or " string ends at its line's end in JavaScript; a template goes on.
      if (quote !== "`") quote = null;
      continue;
    }
    if (c === PIECE) {
      if (quote !== null) return i;
      let a = i - 1;
      while (a >= 0 && joined[a] === PIECE) a--;
      let b = i + 1;
      while (b < joined.length && joined[b] === PIECE) b++;
      if (a < 0) continue;
      // Inside an identifier's escape the piece is the identifier's whatever follows it; after one's "}", as after any
      // of an identifier's characters (get\u{50}<?php ?>assword).
      const escape = escapeBefore(joined, a);
      if (escape === "open") return i;
      if (b < joined.length && (escape === "closed" || identifierAt(joined, a, true)) && identifierAt(joined, b, false)) return i;
      continue;
    }
    if (quote !== null) {
      // A backslash escapes the next character (a line break too: the string goes on), but no piece of PHP.
      if (c === "\\" && joined[i + 1] !== PIECE) i++;
      else if (c === quote) quote = null;
      continue;
    }
    if (c === '"' || c === "`" || (c === "'" && !(LETTER.test(joined[i - 1] ?? "") && LETTER.test(joined[i + 1] ?? "")))) quote = c;
  }
  return -1;
}

/**
 * The password names `joined` (phpHtml's inline HTML joined) spells across a piece of PHP, as [start, end) places in the
 * text (textAt): the inline HTML with its pieces taken out, so the text on a piece's two sides joins, as PHP prints it
 * where the piece prints nothing (getPass<?php ?>word, 'pass<?= '' ?>word'), read as namesPassword reads a name, its
 * escapes too, each where it stands (nameSpans: getPass<?php<line break>?>\u0077ord). A name no
 * piece splits is a line's own.
 */
function splitNames(joined, textAt) {
  if (!joined.includes(PIECE)) return [];
  let bare = "";
  const from = [];
  for (let i = 0; i < joined.length; i++) {
    if (joined[i] === PIECE) continue;
    bare += joined[i];
    from.push(i);
  }
  return acrossPieces(bare, from).map(([s, e]) => [textAt(s), textAt(e) + 1]);
}

/**
 * The names a piece of PHP may complete, as [start, end) places in `text` (getPass<?= 'word' ?>() in a script PHP
 * opens): what a piece prints is never read, and it may print any part of a password name, the whole word
 * too, so a name's text that touches a piece (NAME_TEXT: an identifier's, a string's letters, an escape's characters)
 * may be any part of one: before the piece its start (getPass<?= 'word' ?>, get<?= 'Password' ?>, getTotal<?= $x ?>),
 * after it its end (<?= 'getPass' ?>word), between two pieces a part of it (<?= 'p' ?>as<?= 'sword' ?>). Whatever the
 * text holds, the name is not resolved: it stands over the piece and the text beside it, and so over each line of the
 * piece, whose PHP prints its rest. A piece no name's text touches (a space, a quote, markup, an operator beside it) is
 * none of this: it is read as any piece is (phpHtml), and what it prints is never read. `pieces`: the pieces of PHP,
 * { s, e } in order (phpHtml's, or the ones the text shows: textReading); pieces with nothing between them are one
 * run. One pass, in linear time.
 */
function edgeNames(text, pieces) {
  const out = [];
  // Where the run of pieces before this one ends, or -1 before the first: a name's text between two runs touches both.
  let floor = -1;
  for (let p = 0; p < pieces.length; p++) {
    const { s } = pieces[p];
    let { e } = pieces[p];
    let q = p;
    while (q + 1 < pieces.length && pieces[q + 1].s <= e) e = Math.max(e, pieces[++q].e);
    const a = Math.max(floor, nameTextBack(text, s));
    if (a < s) out.push([a, e]);
    const ceil = q + 1 < pieces.length ? pieces[q + 1].s : text.length;
    const b = Math.min(ceil, nameTextOn(text, e));
    if (b > e) out.push([s, b]);
    floor = e;
    p = q;
  }
  return out;
}

/**
 * HTML's reading of `chars`, a block's inline HTML joined (phpHtml): { codes, doubts }, each <script> element's code as
 * [start, end] and each place where HTML's reading stops being known, as places in `chars`. A PIECE in it is a piece of
 * PHP; `controls` holds the places of those that decide which HTML is printed, or from where (controlsHtml), in order.
 *
 * A piece in HTML's data state is text. A piece anywhere else is a doubt: what it prints is never read, and may be
 * anything, a "<" too, so HTML's state after it may be any state (phpHtml says why no later place is certain). Data
 * stretches are the text between the constructs below; a "<" that starts none of them (a "<" before a space) is data's
 * text, but one a piece stands right after starts a tag's name or an end tag's ("<<?php", "</<?php"). In a <plaintext>
 * only a piece of `controls` is a doubt: HTML never leaves its text, but the HTML after such a piece may be printed
 * without the tag. A piece of `controls` in data is a doubt where a <select> or foreign content is open: the stretch it
 * joins may read otherwise there.
 */
function htmlCodes(chars, controls = []) {
  const n = chars.length;
  const lower = asciiLower(chars);
  const codes = [];
  const doubts = [];
  /** Where the end tag of raw text element or script `name` opens, at or after `from`, or n. */
  const endTag = (name, from) => {
    const re = new RegExp(`</${name}(?=[\\t\\n\\f\\r />])`, "g");
    re.lastIndex = from;
    const m = re.exec(lower);
    return m ? m.index : n;
  };
  // The searches the reading asks at places that only grow: each reads the text once (nextOf).
  const piece = nextOf(chars, PIECE);
  const dashes = nextOf(chars, "-->");
  const bangs = nextOf(chars, "--!>");
  const comments = nextOf(lower, "<!--");
  const scriptTags = nextOf(lower, "<script");
  /** The first piece of PHP in chars[a, b), outside HTML's data state, is a doubt. */
  const outside = (a, b) => {
    const p = piece(a);
    if (p >= 0 && p < b) doubts.push(p);
  };
  /**
   * The tag that opens at `i` ("<" or "</", then a letter) as HTML's tokenizer reads it: { name, end, closes }, its name
   * folded (to its first whitespace, "/", ">" or piece of PHP), `end` after its ">" (or n), and whether a "/>" closes it.
   * After the name: before an attribute's name (B), in one (N), after one (A), before its value (V), in an unquoted
   * value (U), after a quoted one (Q), after a "/" (S); a "=" before an attribute's name starts the name.
   */
  const tagAt = (i) => {
    let j = i + (chars[i + 1] === "/" ? 2 : 1);
    const s = j;
    while (j < n && !/[\t\n\f\r />\u0000]/.test(chars[j])) j++;
    const name = lower.slice(s, j);
    let state = "B";
    for (; j < n; j++) {
      const c = chars[j];
      if (state === "S") {
        if (c === ">") return { name, end: j + 1, closes: true };
        state = "B";
      }
      if (c === ">") return { name, end: j + 1, closes: false };
      if (state === "U") {
        if (HTML_SPACE.test(c)) state = "B";
      } else if (state === "V") {
        if (c === '"' || c === "'") {
          const close = chars.indexOf(c, j + 1);
          if (close < 0) return { name, end: n, closes: false };
          j = close;
          state = "Q";
        } else if (!HTML_SPACE.test(c)) state = "U";
      } else if (HTML_SPACE.test(c)) state = state === "N" || state === "A" ? "A" : "B";
      else if (c === "/") state = "S";
      else state = c === "=" && (state === "N" || state === "A") ? "V" : "N";
    }
    return { name, end: n, closes: false };
  };
  // The elements open in foreign content, outermost first: none outside it. Whether a <select> is open.
  const foreign = [];
  let select = false;
  // The next of `controls` not yet passed.
  let control = 0;
  /** The text chars[a, b) is in data: a piece of `controls` there is a doubt where a <select> or foreign content is open. */
  const data = (a, b) => {
    while (control < controls.length && controls[control] < a) control++;
    for (; control < controls.length && controls[control] < b; control++) if (select || foreign.length) doubts.push(controls[control]);
  };
  let i = 0;
  while (i < n) {
    const lt = chars.indexOf("<", i);
    data(i, lt < 0 ? n : lt);
    if (lt < 0) break;
    if (lower.startsWith("<!--", lt)) {
      // An empty comment, <!--> or <!--->, closes at once; any other at its "-->" or "--!>", or the end.
      if (chars.startsWith("<!-->", lt)) i = lt + 5;
      else if (chars.startsWith("<!--->", lt)) i = lt + 6;
      else {
        const dash = dashes(lt + 4);
        const bang = bangs(lt + 4);
        const k = dash < 0 ? bang : bang < 0 ? dash : Math.min(dash, bang);
        i = k < 0 ? n : k + (k === bang ? 4 : 3);
      }
      outside(lt, i);
      continue;
    }
    // In foreign content "<![CDATA[" opens a section of text that runs to "]]>": from there the rest is read as HTML's own.
    if (foreign.length && chars.startsWith("<![CDATA[", lt)) {
      doubts.push(lt);
      foreign.length = 0;
    }
    // A tag name a piece of PHP starts: what it prints may name any element.
    if (chars[lt + (chars[lt + 1] === "/" ? 2 : 1)] === PIECE) doubts.push(lt);
    if (chars[lt + 1] === "!" || chars[lt + 1] === "?" || (chars[lt + 1] === "/" && !/[A-Za-z]/.test(chars[lt + 2] ?? ""))) {
      const close = chars.indexOf(">", lt + 2);
      i = close < 0 ? n : close + 1;
      outside(lt, i);
      continue;
    }
    if (!/[A-Za-z]/.test(chars[lt + (chars[lt + 1] === "/" ? 2 : 1)] ?? "")) {
      i = lt + 1;
      continue;
    }
    const opens = chars[lt + 1] !== "/";
    const { name: tag, end: close, closes } = tagAt(lt);
    outside(lt, close);
    if (foreign.length) {
      // Foreign content: a tag phpHtml knows is an element, which a "/>" closes, or its end tag or an end tag of one
      // around it. At any other tag HTML's reading is no longer known: from there the rest is read as HTML's own.
      let known = !FOREIGN_UNKNOWN.has(tag);
      if (known && opens && !closes) foreign.push(tag);
      else if (known && !opens) {
        if (foreign.includes(tag)) foreign.length = foreign.lastIndexOf(tag);
        else known = false;
      }
      if (known) {
        i = close;
        continue;
      }
      doubts.push(lt);
      foreign.length = 0;
    }
    // A frameset ignores the script and raw text elements in it, and so may a <select> its raw text elements (but a
    // textarea, which closes it): HTML's reading there is not known.
    if (tag === "select") select = opens;
    if (opens && (tag === "frameset" || (select && HTML_RAW.has(tag) && tag !== "textarea"))) doubts.push(lt);
    if (opens && tag === "script") {
      const end = endTag("script", close);
      const comment = comments(close);
      if (comment >= 0 && comment < end) {
        const opener = scriptTags(comment);
        if (opener >= 0 && opener < end) doubts.push(comment);
      }
      outside(close, end);
      codes.push([close, end]);
      i = end;
      continue;
    }
    if (opens && (tag === "svg" || tag === "math") && !closes) foreign.push(tag);
    if (opens && tag === "plaintext") {
      // HTML never leaves a <plaintext>'s text, so a piece there is text, unless it is one of `controls`: the HTML
      // after it may then be printed without the start tag before it (<?php if ($a): ?><plaintext><?php endif; ?>).
      while (control < controls.length && controls[control] < close) control++;
      if (control < controls.length) doubts.push(controls[control]);
      break;
    }
    i = !opens || !HTML_RAW.has(tag) ? close : endTag(tag, close);
    outside(close, i);
  }
  return { codes, doubts };
}

/**
 * The first place of `needle` in `hay` at or after a place asked, or -1. An answer is kept until a later place passes
 * it, so a reading that asks at places that only grow (htmlCodes) reads `hay` once, where asking indexOf at each place
 * read on to the next one, or to the end, each time.
 */
function nextOf(hay, needle) {
  let from = Infinity;
  let at = -1;
  return (k) => {
    if (k < from || (at >= 0 && at < k)) {
      at = hay.indexOf(needle, k);
      from = k;
    }
    return at;
  };
}

/**
 * The entries as JavaScript reads a PHP file's page scripts: each line's text where its block holds a <script>
 * element's code (phpBlock's js), blank elsewhere, so the JavaScript readers read the scripts where they stand, in
 * JavaScript's plain subset. Each element's code is a program of its own, as a browser runs it: the line its code
 * starts on starts a text (start: startsText), so nothing an element above leaves open (a comment, a string, a group)
 * reads on into it. Read once per entries.
 */
function scriptLines(lines) {
  const state = entriesOf(lines);
  if (state.scripts) return state.scripts;
  state.scripts = lines.map((entry, k) => {
    if (typeof entry.text !== "string" || typeof entry.line !== "number") return { ...entry, text: "" };
    const layout = layoutOf(lines, k, LANGUAGES.php);
    if (layout.php.failure) return { ...entry, text: "" };
    const at = layout.offset[k - layout.start];
    const end = at + entry.text.length;
    // An element's code starts on the line where the first one at or after the line's start (phpHtml gives them in
    // order) starts no later than the line's end: found by halves, not by looking through every element at every line.
    const { scripts } = layout.php;
    let lo = 0;
    for (let hi = scripts.length; lo < hi; ) {
      const mid = (lo + hi) >> 1;
      if (scripts[mid].s < at) lo = mid + 1;
      else hi = mid;
    }
    const start = lo < scripts.length && scripts[lo].s <= end;
    return { ...entry, text: layout.php.js.slice(at, end), ...(start ? { start: true } : {}) };
  });
  return state.scripts;
}

/**
 * commentAt for PHP: each added entry whose line PHP's tokens show wholly comment (every token on it a comment or
 * whitespace, a comment among them), in a block that starts at the file's first line, above where the block's reading
 * stops being known (phpBlock's unknown: layoutOf's unplain). Read once per entries.
 */
function phpComments(lines) {
  const out = new Uint8Array(lines.length);
  const { top } = entriesOf(lines);
  // A line terminator other than LF and CR LF ends what any lexer establishes in its block (Comments a code marker may
  // pass over): PHP reads U+2028 in a comment as its text, but no line at or after one is passed over.
  let unusual = false;
  for (let k = 0; k < lines.length; k++) {
    const entry = lines[k];
    if (top[k] === k) unusual = false;
    if (typeof entry.text === "string" && UNUSUAL_TERMINATOR.test(entry.text)) unusual = true;
    if (unusual || typeof entry.text !== "string" || entry.context || typeof entry.line !== "number" || lines[top[k]].line > 1) continue;
    const layout = layoutOf(lines, k, LANGUAGES.php);
    if (layout.php.failure || (layout.unplain >= 0 && k >= layout.unplain)) continue;
    const { b } = layout;
    const from = layout.offset[k - layout.start];
    const to = from + entry.text.length;
    let comment = false;
    let code = false;
    for (let t = tokenAfter(b, from); t < b.toks.length && b.toks[t].s < to && !code; t++) {
      const { k: kind } = b.toks[t];
      if (kind === "comment") comment = true;
      else if (kind !== "ws" && kind !== "nl") code = true;
    }
    if (comment && !code) out[k] = 1;
  }
  return out;
}

// ----- The readers ------------------------------------------------------------------------------

/**
 * Whether a code text's first line gives a password a literal: true, false or "more". The first line is read with the
 * lines a backslash at its end continues (logicalFirstLine), and one that ends in a password word's start before such
 * a backslash waits for the next line (nameGoesOn).
 */
function readCode(text, L, depth, end, before = null) {
  const first = logicalFirstLine(text);
  if (!mentions(first)) return end === "more" && nameGoesOn(first) ? "more" : false;
  let r;
  if (L.id === "php") {
    // PHP's text is read by PHP's own tokens (phpReader); one PHP's tokenizer did not read counts.
    r = phpReader(text, depth, end, before);
    if (r === null) return true;
  } else {
    // A text that starts inside a string or a comment a line above opens is lexed from there (leadOf); one whose start
    // the lexer cannot place counts.
    const lead = before ? before.lead(L) : "";
    if (lead === null) return true;
    // A line that starts with "*" continues a block comment: its text is a comment's.
    const doc = L.block && end !== "comment" && !lead ? /^([ \t]*)\*(?!\/)(?=[ \t]|$)/.exec(text) : null;
    if (doc && depth < READ_DEPTH) return readCode(`${doc[1]} ${text.slice(doc[0].length)}`, L, depth + 1, "comment");
    // The text reads on from the last token above it (contextAt), so a "/", a "(" or a "{" at its start reads as the
    // lines above leave it. Where no line above is known, a "/" at its start may divide or open a regular expression;
    // a comment's or a string's text read as code starts an operand.
    const prev = before ? before.prev(L) : null;
    const above = before && L.regex ? before.above(L) : null;
    const known = depth > 0 || prev !== null || (above !== null && above.top);
    // A "/", a group and a "++" the block's lexing holds at the same place read as the block reads them (blockTokens),
    // and the token before the last one above tells a keyword from a property's name (a.return<line break>/ 2).
    const at = above ? blockTokens(above.b, above.offset, lead.length) : null;
    r = reader(text, L, depth, end, before, lead, prev, { known, nl: aboveBreak(lead, prev), at, prev2: before ? before.prev2(L) : null });
  }
  // Where JavaScript may read a "/" otherwise than the lexer does (a regular expression or a division), or a template's
  // state stops being known, the text's tokens may not be JavaScript's: it counts.
  if (r.toks.unsure >= 0) return true;
  let more = false;
  const decide = (verdict) => {
    if (verdict === "more") more = true;
    return verdict === true;
  };
  for (const target of targetsOf(r)) {
    if (target.more) {
      more = true;
      continue;
    }
    // A TypeScript declaration the reader cannot read counts; one the text ends inside is open.
    if (target.unread) return true;
    if (target.open !== undefined) {
      if (decide(verdictAtEnd(r, OPEN, target.open))) return true;
      continue;
    }
    if (target.declared !== undefined) {
      const { declared } = target;
      if (declared.unread) return true;
      if (declared.open && decide(verdictAtEnd(r, OPEN, target.at))) return true;
      if (declared.more && decide(verdictAtEnd(r, UNENDED, target.at))) return true;
      if (declared.value === undefined) continue;
      const value = readValue(r, declared.value, 0, { flag: target.flag, field: target.field, structure: true, key: target.key, helper: target.helper });
      const open = value.open === true ? OPEN : value.open;
      if (value.lit || (open && decide(verdictAtEnd(r, open, declared.value)))) return true;
      continue;
    }
    if (target.shell !== undefined) {
      if (shellWord(r.text, target.shell).lit) return true;
      continue;
    }
    if (target.accessor !== undefined) {
      const value = accessorValue(r, target.accessor, target.def, target.reading);
      if (value.lit || (value.open && decide(verdictAtEnd(r, OPEN, target.accessor)))) return true;
      continue;
    }
    // A target may name more than one reading of its value's quotes: it counts where either reading counts.
    for (const reading of Array.isArray(target.reading) ? target.reading : [target.reading]) {
      const value =
        target.args !== undefined
          ? readItems(r, target.args, 0, { reading }, UNKNOWN_CALL)
          : readValue(r, target.value, 0, {
              flag: target.flag,
              field: target.field,
              reading,
              tuple: target.tuple,
              group: target.item,
              structure: true,
              key: target.key,
              helper: target.helper,
            });
      const open = value.open === true ? OPEN : value.open;
      if (value.lit || (open && decide(verdictAtEnd(r, open, target.args ?? target.value)))) return true;
    }
  }
  for (let i = 0; i < r.toks.length && r.toks[i].s < r.firstEnd; i++) {
    const t = r.toks[i];
    if (decide(t.k === "str" ? stringReading(r, i) : t.k === "comment" ? commentReading(r, i) : t.k === "html" ? htmlReading(r, i) : false)) return true;
    if (t.lead && t.k === "str" && decide(continuedReading(r, i))) return true;
  }
  // A string's text and a comment's were read above, on their own.
  const read = r.toks.filter((t) => (t.k === "str" || t.k === "comment") && t.s < r.firstEnd);
  if (envAssignment(text, (k) => within(read, k))) return true;
  return more ? "more" : false;
}

/** Whether a code text is wholly one value that holds no literal (what an interpolation holds). */
function expressionIsReference(inner, language, depth) {
  // A PHP expression is read only by PHP's own tokens (phpParts); a text that holds one is not read here.
  if (depth > READ_DEPTH || language === "php") return false;
  const r = reader(inner, LANGUAGES[language], depth, "change", null, "", null, { known: true, expr: true });
  if (r.toks.unsure >= 0) return false;
  const start = skip(r, 0, true);
  if (start >= r.toks.length) return false;
  const value = readValue(r, start, 0, { group: true });
  // The text's end is the value's.
  return !value.lit && (!value.open || value.open === UNENDED) && skip(r, value.end, true) >= r.toks.length;
}

/**
 * The reading for `language`: "php", "js", "py", "text", or "any" (a file whose language the map does not know: a
 * value counts where the JavaScript, Python and text readings all count; PHP is read only by PHP's tokens, and no PHP
 * file is read as "any"). `before`: what the lines above the text tell of it (contextAt), or null.
 */
function readsLiteral(text, language, end, before = null) {
  if (language === "text") return readText(text, end);
  if (language === "yaml") return readText(text, end, 0, true);
  if (language !== "any") return readCode(text, LANGUAGES[language], 0, end, before);
  let more = false;
  for (const id of ["js", "py", "text"]) {
    const verdict = id === "text" ? readText(text, end) : readCode(text, LANGUAGES[id], 0, end, before);
    if (verdict === false) return false;
    if (verdict === "more") more = true;
  }
  return more ? "more" : true;
}

// ----- The plain subset -------------------------------------------------------------------------
//
// The readers above trust their own reading only in the plain subset; they are not refined against unusual syntax.
// Outside it a line that names a password counts, a reference too, so a miss there is harmless (default deny). Inside
// it a plain password reference in ordinary code is no hit and needs no person.
//
// A block of a file's entries (the lines markerHits was handed whose numbers follow on) is plain up to its first
// unusual line (unplainOf):
// - a line that holds a line terminator other than LF and a CR LF: a bare CR, U+0085, U+2028 or U+2029
//   (UNUSUAL_TERMINATOR), in every file;
// - in JavaScript and Python, the line where the lexer's state stops being known (layoutOf: unplain): a template
//   inside a JavaScript template's interpolation, a comment or a regular expression there, a quote a line break ends
//   there or a close that opens nothing (templateEnd); a "/" the lexer cannot read whose two readings may leave its
//   line differently (lex: far); and a line that starts where the lexer's state is unknown (leadOf: null), as past
//   LEAD_FRAMES groups and keywords open in an interpolation;
// - in PHP, whose state PHP's own tokens give (PHP's tokens, above), the line where the block's reading stops being
//   known (phpBlock's unknown): a "<?" in inline HTML, which short_open_tag decides; a script element whose code holds
//   "<!--" and then "<script"; two script elements on one line; and where HTML's reading of the inline HTML stops
//   being known (phpHtml: foreign content, a frameset, a literal NUL, and a piece of PHP anywhere but in HTML's data
//   state, whose output may finish a delimiter and open any other: htmlCodes; and a piece in data inside
//   an identifier or a string, whose output joins the text on its two sides: insidePiece, and a password name read
//   across a piece, from the name's start);
// - in a PHP page's explicit <script> element, read as JavaScript, each element its own program (scriptLines): the line
//   where JavaScript's reading of the element's code leaves the plain subset.
// Nesting and length past the readers' other limits need no line of their own: a value read past READ_DEPTH, a
// statement still open after WINDOW_LINES lines or WINDOW_CHARS characters, or open past STATEMENT_CHARS, more than
// OPEN_STATEMENTS open around a run and a read back past READ_BACK_CHARS count, a reference too.
// Inside the plain subset the readers' verdicts stand, and a name given wholly a reference is no hit. From a block's
// first unusual line on no reader clears a line (outsidePlain): every added line there that names a password counts
// (namesPassword: as written, or with its backslashes and unusual line terminators taken out; in PHP also with the
// pieces of PHP on it taken out, or where a name spelt across a piece over lines stands on it: namedOf), and so does every added
// line below one there that names one, below a statement a line naming one starts above it and runs on into it, or in
// a block that starts below the file's first line; above it, a line that names one counts where its statement runs
// on into it. A "/" the lexer cannot read counts every line read over it. A block that starts below the
// file's first line is read as starting in code (check.mjs hands every line above a run, so its blocks start there).
// This file's own templates hold no template or regular expression in an interpolation, so it stays in the plain
// subset (tests/doors-plain-subset.test.mjs).

/** A backslash or an unusual line terminator: what namesPassword takes out of a name. */
const NAME_NOISE = /[\\\r\u0085\u2028\u2029]/;
const NAME_NOISE_ALL = new RegExp(NAME_NOISE.source, "g");

/**
 * Whether `text` names a password as the plain subset's gate reads a name: as mentions() does, or once every backslash
 * and every unusual line terminator is taken out of it (pa\<U+2028>ssword, a JavaScript string's password).
 */
const namesPassword = (text) => mentions(text) || (NAME_NOISE.test(text) && mentions(text.replace(NAME_NOISE_ALL, "")));

/**
 * Where `text` names a password as namesPassword reads a name, each as [start, end) in `text`: as written; with its
 * escapes read (decodeName) and that NFKC-normalized, as mentions reads one; and with every backslash and unusual line
 * terminator taken out (NAME_NOISE), and that NFKC-normalized. Each reading keeps, for each of its characters, where
 * in `text` it was read from, so a name read through an escape stands where the escape does (a name
 * a piece of PHP splits over lines, its rest spelt with an escape: getPass<?php<line break>?>\u0077ord). NFKC is read a
 * code point at a time, so each character keeps its place.
 */
function nameSpans(text) {
  if (!namesPassword(text)) return [];
  const spans = [];
  const scan = (read, places) => {
    const re = new RegExp(PASSWORD_MENTION.source, "gi");
    for (let m = re.exec(read); m; m = re.exec(read)) {
      const a = m.index;
      const b = a + m[0].length - 1;
      spans.push(places ? [places[2 * a], places[2 * b + 1]] : [a, b + 1]);
    }
  };
  scan(text, null);
  const readings = [];
  if (/[\\\u0080-\uffff]/.test(text)) {
    const places = [];
    readings.push([decodeName(text, places), places]);
  }
  if (NAME_NOISE.test(text)) {
    let read = "";
    const places = [];
    for (let i = 0; i < text.length; i++) {
      if (NAME_NOISE.test(text[i])) continue;
      read += text[i];
      places.push(i, i + 1);
    }
    readings.push([read, places]);
  }
  for (const [read, places] of readings) {
    scan(read, places);
    if (!/[^\x00-\x7f]/.test(read)) continue;
    let normal = "";
    const kept = [];
    for (let i = 0; i < read.length; ) {
      const n = read.codePointAt(i) > 0xffff ? 2 : 1;
      const ch = read.charCodeAt(i) < 0x80 ? read[i] : read.slice(i, i + n).normalize("NFKC");
      normal += ch;
      for (let u = 0; u < ch.length; u++) kept.push(places[2 * i], places[2 * (i + n - 1) + 1]);
      i += n;
    }
    scan(normal, kept);
  }
  return spans;
}

/**
 * The names nameSpans finds in `bare`, a text with its pieces of PHP taken out, that stand across a piece: as [first,
 * last] places before the pieces were taken out (`from`: each of bare's characters' place).
 */
function acrossPieces(bare, from) {
  const out = [];
  for (const [s, e] of nameSpans(bare)) if (from[e - 1] - from[s] !== e - 1 - s) out.push([from[s], from[e - 1]]);
  return out;
}

/**
 * What a name's text may hold beside a piece of PHP, one code point (codeBefore, codeAt): every character an identifier
 * holds (IDENTIFIER_CHAR, the one list of them: a letter, a digit, a connector such as "_" or U+203F, ZWNJ and ZWJ, a
 * character such as U+2118 or U+00B7 that Unicode adds to identifiers, "$", an escape's backslash), a mark, an escape's
 * braces, the noise namesPassword takes out (NAME_NOISE), and a lone surrogate (half of a code point the text cut). A
 * symbol past U+FFFF (an emoji) is none. Built from IDENTIFIER_CHAR, it holds every character an identifier does
 * (U+203F, U+200C, U+200D and U+2118 too), so none of them beside a piece reads as no name's.
 */
const NAME_TEXT = new RegExp(`^(?:${IDENTIFIER_CHAR.source}|${NAME_NOISE.source}|[\\p{M}{}\\ud800-\\udfff])$`, "u");
/** How far a name's text is read back from a piece of PHP, or on after one, for where a name the piece splits may start or end. */
const NAME_WINDOW = 64;
/** Whether text[i], a line break, is one a backslash escapes, which decodeName takes out of a name (\ and LF, \ and CR LF). */
const escapedBreak = (text, i) => text[i] === "\n" && (text[i - 1] === "\\" || (text[i - 1] === "\r" && text[i - 2] === "\\"));

/** Where the name's text (NAME_TEXT) that ends at `i` in `text` starts, read back at most NAME_WINDOW characters, a code point at a time (codeBefore). */
function nameTextBack(text, i) {
  let a = i;
  while (a > 0 && i - a < NAME_WINDOW) {
    const c = codeBefore(text, a);
    if (!NAME_TEXT.test(c) && !escapedBreak(text, a - 1)) break;
    a -= c.length;
  }
  return a;
}

/** Where the name's text (NAME_TEXT) that starts at `i` in `text` ends, read on at most NAME_WINDOW characters, a code point at a time (codeAt). */
function nameTextOn(text, i) {
  let b = i;
  while (b < text.length && b - i < NAME_WINDOW) {
    const c = codeAt(text, b);
    if (!NAME_TEXT.test(c) && !(b > i && escapedBreak(text, b))) break;
    b += c.length;
  }
  return b;
}

/** How many characters at `i` the line break PHP's closing tag takes with it is ("\n", "\r\n" or "\r"), or 0. */
const closeBreak = (text, i) => (text[i] === "\n" ? 1 : text[i] === "\r" ? (text[i + 1] === "\n" ? 2 : 1) : 0);

/**
 * The password names a block's text may spell across a piece of PHP, as [start, end) in `text`, read from the text
 * alone: where PHP's tokenizer did not read it (phpBlock's failure), and where short_open_tag decides what is PHP (a
 * "<?" in inline HTML), a piece over lines too (getPass<?php<line break>?>word, which PHP_PIECE_TEXT, read a line
 * at a time, does not join). A piece is taken to run from a "<?" that opens one to the first "?>" after it,
 * with the line break PHP's closing tag takes (closeBreak), or to the text's end; the text with every piece taken out
 * is read for a name that stands across one (nameSpans). Which "<?" opens a piece short_open_tag decides, and the text
 * does not tell how PHP runs, so it is read both ways (a "<?" in a script's string, const x = '<?';, read as a piece's
 * start runs to the "?>" of a later piece and takes a name's start with it): with
 * every "<?" a piece's start, and with only "<?php" and whitespace (or the text's end) and "<?=" (opensLong), and each
 * name either reading finds counts. A "?>" inside a string, a block comment or a heredoc ends no piece, so where a
 * piece's text up to that "?>" holds a quote, "/*" or "<<<", it may end at a later one. A piece may
 * print any part of a name, so a name's text that touches a piece the text shows counts with it (edgeNames), and so
 * does one after any later "?>" of a piece whose end the text does not tell, with that piece from its start
 * (<?php $x = '?>'; echo 'p'; ?>as<?= 'sword' ?>); where a name's text stands right before such a
 * piece, from the piece to the text's end counts, all it may run over. Where the text starts below a file's first line
 * (`below`), it may start inside a piece whose start, and what stands before it, no line handed shows: from the text's
 * start to the last name's text after a "?>" counts. Each reading is one pass, in linear time.
 */
function textNames(text, below) {
  return [...textReading(text, below, true), ...textReading(text, below, false)];
}

/** "<?php" and whitespace or the text's end, or "<?=": a "<?" that opens PHP's code with short_open_tag off too. */
const LONG_OPEN = /<\?(?:php(?:[ \t\r\n]|$)|=)/iy;
/** Whether the "<?" at `o` in `text` opens PHP's code with short_open_tag off (LONG_OPEN). */
const opensLong = (text, o) => {
  LONG_OPEN.lastIndex = o;
  return LONG_OPEN.test(text);
};

/** textNames' reading of `text` with every "<?" a piece's start (`short`), or only those that open one with short_open_tag off. */
function textReading(text, below, short) {
  let bare = "";
  // bare's last characters, as many as nameTextBack reads back over (and escapedBreak's three before), kept apart: bare
  // grows by joins, and reading a joined string's characters copies all of it, at every piece (quadratic).
  let tail = "";
  const from = [];
  /** Where the piece the text shows whose "?>" is at `c` (or none: -1) ends: past the line break its closing tag takes. */
  const closeEnd = (c) => (c < 0 ? text.length : c + 2 + closeBreak(text, c + 2));
  // The pieces the text shows, { s, e } in order (edgeNames). Below a file's first line the text starts in a piece.
  const pieces = below ? [{ s: 0, e: closeEnd(text.indexOf("?>")) }] : [];
  // The starts of the pieces whose end the text does not tell, in order: below a file's first line the piece the text
  // starts in, whose start, and what stands before it, no line handed shows. `seen`: the first such piece a name's
  // text stands right before (bare's, across the pieces before it), or -1.
  const untold = below ? [0] : [];
  let seen = -1;
  for (let i = 0; i < text.length; ) {
    let o = text.indexOf("<?", i);
    while (o >= 0 && !short && !opensLong(text, o)) o = text.indexOf("<?", o + 1);
    const stop = o < 0 ? text.length : o;
    for (let j = i; j < stop; j++) {
      bare += text[j];
      from.push(j);
    }
    tail = (tail + text.slice(Math.max(i, stop - NAME_WINDOW - 3), stop)).slice(-(NAME_WINDOW + 3));
    if (o < 0) break;
    const c = text.indexOf("?>", o + 2);
    if (/['"`]|\/\*|<<</.test(text.slice(o, c < 0 ? text.length : c))) {
      untold.push(o);
      if (seen < 0 && nameTextBack(tail, tail.length) < tail.length) seen = o;
    }
    i = closeEnd(c);
    pieces.push({ s: o, e: i });
  }
  // A name a piece may complete stands over it and the name's text beside it (edgeNames).
  const names = [...acrossPieces(bare, from).map(([s, e]) => [s, e + 1]), ...edgeNames(text, pieces)];
  if (!untold.length) return names;
  // A piece whose end the text does not tell may end at any "?>" after it, and print any part of a name there
  // (<?php $x = '?>'; echo 'p'; ?>as<?= 'sword' ?>): a name's text after such a "?>" stands
  // with the piece from its start (the nearest such piece at or before the "?>"), and with the next piece, which
  // edgeNames reads it before. Below a file's first line, what stands before the piece the text starts in is not seen,
  // so from the text's start to the last such name's text counts.
  let last = -1;
  for (let c = text.indexOf("?>", untold[0]), u = 0; c >= 0; c = text.indexOf("?>", c + 2)) {
    while (u + 1 < untold.length && untold[u + 1] <= c) u++;
    const at = closeEnd(c);
    const end = nameTextOn(text, at);
    if (end <= at) continue;
    names.push([untold[u], end]);
    last = end;
  }
  if (below && last >= 0) names.push([0, last]);
  // A name's text before such a piece stands with all the piece may run over: to a later "?>", or to the text's end,
  // where it never closes, its PHP printing the name's rest from any line.
  if (seen >= 0) names.push([seen, text.length]);
  return names;
}

/** Mark in `own` each entry of `layout`'s block (layoutOf) that one of `spans`, [start, end) places in its text, stands on. */
function markSpans(layout, spans, own) {
  if (!spans.length) return;
  const { start, end, offset } = layout;
  // The block's entry, from its start, that place `p` stands on: found by halves.
  const entryAt = (p) => {
    let lo = 0;
    for (let hi = end - start; lo < hi; ) {
      const mid = (lo + hi + 1) >> 1;
      if (offset[mid] <= p) lo = mid;
      else hi = mid - 1;
    }
    return lo;
  };
  const marks = new Int32Array(end - start + 2);
  for (const [s, e] of spans) {
    if (e <= s) continue;
    marks[entryAt(s)]++;
    marks[entryAt(e - 1) + 1]--;
  }
  for (let k = 0, depth = 0; k <= end - start; k++) {
    depth += marks[k];
    if (depth > 0) own[start + k] = 1;
  }
}

/** A piece of PHP as its text shows one, where PHP's tokenizer did not read the text: "<?php" or "<?=" to "?>" on the line. */
const PHP_PIECE_TEXT = /<\?(?:php(?=[\s]|$)|=)[\s\S]*?(?:\?>|$)/gi;

/**
 * The entries that name a password in reading `language` (namesPassword, or mentionsOf's names a backslash continues
 * over lines), read once per language: own[k], whether entry k names one, and last[k], the nearest entry of k's block, at
 * or above k, that names one, or -1. In PHP an entry also names one where its inline HTML spells one across a piece of
 * PHP: where the line's text with the pieces of PHP on it taken out names one, as namesPassword reads a name
 * (getPass<?php ?>word, getPass<?php ?>\u0077ord), on each line a name spelt across a piece over lines stands on
 * (phpHtml's names, escapes read: nameSpans), and on each line of a piece and the name's text that touches it, a name
 * the piece may complete (edgeNames),
 * and, where PHP's tokenizer did not read the block, where its text with each piece the text shows on it taken out
 * names one (PHP_PIECE_TEXT), and on each line a name the block's text may spell across a piece stands on, a piece
 * over lines too (textNames).
 */
function namedOf(lines, language) {
  const state = entriesOf(lines);
  const known = state.named.get(language);
  if (known) return known;
  const own = new Uint8Array(lines.length);
  for (const m of state.mentions) own[m] = 1;
  if (language === "php") splitNamed(lines, own);
  const last = new Int32Array(lines.length).fill(-1);
  for (let k = 0; k < lines.length; k++) {
    const { text } = lines[k];
    if (!own[k] && typeof text === "string" && NAME_NOISE.test(text) && namesPassword(text)) own[k] = 1;
    last[k] = own[k] ? k : state.top[k] < k ? last[k - 1] : -1;
  }
  const named = { own, last };
  state.named.set(language, named);
  return named;
}

/** Mark in `own` each PHP entry a password name spelt across a piece of PHP stands on (namedOf), block by block. */
function splitNamed(lines, own) {
  const { top } = entriesOf(lines);
  for (let start = 0; start < lines.length; start++) {
    if (top[start] !== start || typeof lines[start].text !== "string" || typeof lines[start].line !== "number") continue;
    const layout = layoutOf(lines, start, LANGUAGES.php);
    if (layout.php.failure) {
      for (let k = start; k <= layout.end; k++) if (namesPassword(lines[k].text.replace(PHP_PIECE_TEXT, ""))) own[k] = 1;
      markSpans(layout, textNames(layout.b.text, !startsText(lines[start])), own);
      continue;
    }
    const { names = [], pieces = [] } = layout.php;
    markSpans(layout, names, own);
    // Each line a piece of PHP stands on, read with its pieces taken out: the pieces are in order, read once.
    const { text } = layout.b;
    for (let k = start, p = 0; k <= layout.end && p < pieces.length; k++) {
      const from = layout.offset[k - start];
      const to = from + lines[k].text.length;
      while (p < pieces.length && pieces[p].e <= from) p++;
      if (own[k] || p >= pieces.length || pieces[p].s >= to) continue;
      let joined = "";
      let at = from;
      for (let q = p; q < pieces.length && pieces[q].s < to; q++) {
        if (pieces[q].s > at) joined += text.slice(at, pieces[q].s);
        at = Math.max(at, Math.min(to, pieces[q].e));
      }
      if (namesPassword(joined + text.slice(at, to))) own[k] = 1;
    }
  }
}

/**
 * The first entry at which `lines[index]`'s block leaves the plain subset in reading `language` (above), or -1 where
 * all of it is plain; read once per block and language.
 */
function unplainOf(lines, index, language) {
  const state = entriesOf(lines);
  const known = perLanguage(state, "unplain", language);
  const start = state.top[index];
  if (known.has(start)) return known.get(start);
  let u = -1;
  for (let k = start; k < lines.length && state.top[k] === start; k++) {
    if (holdsUnusualTerminator(lines[k].text)) {
      u = k;
      break;
    }
  }
  if (Object.hasOwn(LANGUAGES, language)) {
    const lexed = layoutOf(lines, start, LANGUAGES[language]).unplain;
    if (lexed >= 0 && (u < 0 || lexed < u)) u = lexed;
  }
  known.set(start, u);
  return u;
}

/**
 * Whether a statement that an unchanged line naming a password starts above `u` (the block's first unusual entry) is
 * still open at the line before it, read in the plain part (openThrough): one that runs on into the unusual lines. An
 * added line that starts one counts itself (outsidePlain). Read once per block and language.
 */
function crossesInto(lines, u, language) {
  const state = entriesOf(lines);
  const known = perLanguage(state, "crossing", language);
  if (known.has(u)) return known.get(u);
  const { own } = namedOf(lines, language);
  let crosses = false;
  for (let m = u - 1; m >= state.top[u] && !crosses; m--) crosses = own[m] === 1 && lines[m].context === true && openThrough(lines, m, u - 1, language) !== false;
  known.set(u, crosses);
  return crosses;
}

/** A line that holds nothing but whitespace, closing brackets, ";" and ",": it gives nothing a value. */
const INERT_LINE = /^[\s)\]};,]*$/;

/**
 * Whether added entry `k` counts because its block leaves the plain subset (above), at its first unusual entry u. Above
 * u the readers' reading stands, but for a line that names a password and starts a statement still open at u. From u on
 * no reader clears a line: one that names a password counts, and so does one (but an inert one: INERT_LINE) below an
 * unchanged line naming one there (the nearest; an added one counts itself), below a statement an unchanged line naming
 * one starts above u and runs on into u (crossesInto), or in a block that starts below the file's first line. A block
 * where nothing up to the entry names a password, and that starts at the file's first line, is not read for it.
 */
function outsidePlain(lines, k, language) {
  const { own, last } = namedOf(lines, language);
  const state = entriesOf(lines);
  const top = state.top[k];
  const below = !startsText(lines[top]);
  if (last[k] < 0 && !below) return false;
  const u = unplainOf(lines, k, language);
  if (u < 0) return false;
  if (k < u) return own[k] === 1 && openThrough(lines, k, u - 1, language) !== false;
  if (own[k] === 1) return true;
  if (INERT_LINE.test(lines[k].text)) return false;
  if (below) return true;
  const named = k > top ? last[k - 1] : -1;
  if (named >= u) return lines[named].context === true;
  return u > top && crossesInto(lines, u, language);
}

// ----- Comments a code marker may pass over ------------------------------------------------------
//
// A code marker (station.mjs markerHits) passes over a line of the diff only where the lexer has established that the
// line is wholly comment (commentAt); any doubt keeps its text. Established means, in every language: the line's block
// starts at the file's first line, and the block holds no line terminator other than LF and CR LF up to and through
// the line. Then, in JavaScript: the block is plain up to and through the line (unplainOf), no quote a line break
// leaves open stands above the line in the block (brokenQuote), and every token on it is a comment or whitespace. In
// PHP, PHP's own tokens (phpComments): every token on the line is a comment or whitespace, at least one a comment, so
// it holds no code, no string, no heredoc or nowdoc and no inline HTML, above where the block's reading stops being
// known (phpBlock's unknown); where PHP's tokenizer did not read the file, no line is. A YAML line is established as a comment where it
// opens with "#" outside a block scalar's lines and a quoted scalar a line above leaves open (yamlComments).

/** The first token of reader `r` that ends after character `at`, by a binary search. */
function tokenAfter(r, at) {
  const { toks } = r;
  let lo = 0;
  let hi = toks.length;
  while (lo < hi) {
    const mid = (lo + hi) >> 1;
    if (toks[mid].e <= at) lo = mid + 1;
    else hi = mid;
  }
  return lo;
}

/**
 * Whether the lexer of `language` ("php", "js" or "yaml") has established that added entry `index` of `handed`
 * (markerHits' entries) is wholly comment (above). Any other language establishes none. Read once per entry; YAML's
 * once per entries.
 */
export function commentAt(handed, index, language) {
  const lines = ownLines(handed);
  const entry = lines[index];
  if (!entry || typeof entry.text !== "string" || entry.context || typeof entry.line !== "number") return false;
  const state = entriesOf(lines);
  if (language === "yaml") {
    state.yaml ??= yamlComments(lines, new Uint8Array(lines.length));
    return state.yaml[index] === 1;
  }
  if (language === "php") {
    state.php ??= phpComments(lines);
    return state.php[index] === 1;
  }
  if (language !== "js") return false;
  const known = perLanguage(state, "comments", language);
  if (!known.has(index)) known.set(index, establishedComment(lines, index, LANGUAGES.js));
  return known.get(index);
}

/**
 * Where the first quote of reader `r` stands that a line break breaks before its close (a str token neither closed nor
 * running to the text's end), or -1: from there on the lexer's reading of the text's comments is not established. Read
 * once per reader.
 */
function brokenQuote(r) {
  if (r.broken === undefined) {
    const tok = r.toks.find((t) => t.k === "str" && !t.closed && t.e < r.text.length);
    r.broken = tok ? tok.s : -1;
  }
  return r.broken;
}

/** commentAt for JavaScript: entry `k` of `lines` (own lines) read in its block by language `L`. */
function establishedComment(lines, k, L) {
  const entry = lines[k];
  if (!startsText(lines[entriesOf(lines).top[k]])) return false;
  const u = unplainOf(lines, k, L.id);
  if (u >= 0 && u <= k) return false;
  const layout = layoutOf(lines, k, L);
  const { b } = layout;
  const at = layout.offset[k - layout.start];
  const end = at + entry.text.length;
  const broken = brokenQuote(b);
  if (broken >= 0 && broken < at) return false;
  let comment = false;
  let t = tokenAfter(b, at);
  for (; t < b.toks.length && b.toks[t].s < end; t++) {
    const tok = b.toks[t];
    if (tok.k === "comment") comment = true;
    else if (tok.k !== "ws" && tok.k !== "nl") return false;
  }
  return comment;
}

/** Where the quoted scalar whose text starts at `text[i]` closes (after its quote `q`: '' is a quote in '...'), or -1. */
function yamlQuoteEnd(text, i, q) {
  for (let j = i; j < text.length; j++) {
    if (q === '"' && text[j] === "\\") j++;
    else if (text[j] === q) {
      if (q === "'" && text[j + 1] === "'") j++;
      else return j + 1;
    }
  }
  return -1;
}

/**
 * A YAML line from `text[i]`, read as YAML 1.2 reads where a scalar starts (at the line's start, after "- ", "? ", ": ",
 * "[", "{" or ","; an anchor or a tag keeps it there): { quote } where a quoted scalar it opens is still open at its
 * end, { block } where it ends with a block scalar's indicator ("|" or ">", maybe its own indicators and a comment).
 * A "#" after a space outside quotes ends the line's reading.
 */
function yamlLine(text, i) {
  let value = true;
  while (i < text.length) {
    const c = text[i];
    if (c === " " || c === "\t") i++;
    else if (c === "#" && (i === 0 || text[i - 1] === " " || text[i - 1] === "\t")) break;
    else if (value && (c === '"' || c === "'")) {
      const e = yamlQuoteEnd(text, i + 1, c);
      if (e < 0) return { quote: c, block: false };
      i = e;
      value = false;
    } else if (value && (c === "|" || c === ">") && /^[|>][-+0-9]*[ \t]*(?:#.*)?$/.test(text.slice(i))) return { quote: null, block: true };
    else if (value && (c === "&" || c === "!")) {
      while (i < text.length && text[i] !== " " && text[i] !== "\t") i++;
    } else if ((c === "-" || c === "?" || c === ":") && (i + 1 >= text.length || text[i + 1] === " " || text[i + 1] === "\t")) {
      value = true;
      i++;
    } else if (c === "[" || c === "{" || c === ",") {
      value = true;
      i++;
    } else {
      value = false;
      i++;
    }
  }
  return { quote: null, block: false };
}

/** commentAt for YAML: `out` with each added line a "#" opens outside a block scalar and an open quoted scalar marked. */
function yamlComments(lines, out) {
  const state = entriesOf(lines);
  let known = false;
  let scalar = -1;
  let quote = null;
  for (let k = 0; k < lines.length; k++) {
    const { text } = lines[k];
    if (typeof text !== "string") {
      known = false;
      continue;
    }
    if (state.top[k] === k) {
      known = lines[k].line === 1;
      scalar = -1;
      quote = null;
    }
    if (!known || UNUSUAL_TERMINATOR.test(text)) {
      known = false;
      continue;
    }
    const indent = /^ */.exec(text)[0].length;
    if (scalar >= 0) {
      if (!text.trim() || indent > scalar) continue;
      scalar = -1;
    }
    let from = indent;
    if (quote) {
      const e = yamlQuoteEnd(text, 0, quote);
      if (e < 0) continue;
      quote = null;
      from = e;
    } else if (text.slice(indent).trimStart().startsWith("#")) {
      if (!lines[k].context && typeof lines[k].line === "number") out[k] = 1;
      continue;
    }
    const read = yamlLine(text, from);
    if (read.quote) quote = read.quote;
    else if (read.block) scalar = indent;
  }
  return out;
}

/**
 * Whether `lines[k + 1]` is the line after `lines[k]` in the file, in the same text: a page script's first line
 * (startsText) starts its own.
 */
const followsOn = (lines, k) => {
  const next = lines[k + 1];
  return Boolean(next) && typeof next.text === "string" && typeof lines[k].line === "number" && next.line === lines[k].line + 1 && next.start !== true;
};

/**
 * Whether the file goes on below `lines[k]` with lines its entries do not hold: the entry is marked cut (a caller that
 * passes a partial patch hands fewer lines than the file holds below it: addedWithContext's `after`), or the next
 * entry's line number is further on than the next line's (a gap). The entries' last line is the file's last unless
 * marked so. A line read on to the end of what it opens reads a cut as no end.
 */
const cutAt = (lines, k) => {
  const next = lines[k + 1];
  return lines[k].cut === true || (typeof next?.line === "number" && typeof lines[k].line === "number" && next.line > lines[k].line + 1);
};

/**
 * Whether a block whose first entry is `entry` starts where its text does, so nothing stands above it: at the file's
 * first line (or a line with no number), or at a page script's first line (scriptLines: start).
 */
const startsText = (entry) => entry.line <= 1 || entry.start === true;

/**
 * Read from `lines[index]` (markerHits' {line, text} entries) on through the next lines, the entries whose line
 * numbers follow on, added or unchanged, as far as the statements it opens run. The line is read alone first; only
 * a statement that runs past it is read again, once, with up to WINDOW_LINES of the next lines and
 * WINDOW_CHARS characters, so each line costs two reads at most. A statement still open there counts.
 *
 * markerHits may also hand the unchanged lines above and below a run of added ones ({line, text, context: true},
 * changeset.mjs addedWithContext, which hands all of them): a context line is never a hit; an added line reads on
 * into the unchanged lines below it through the end of what it opens, and the first added line of
 * a run reads back over every line above it in its block (readsBack), so an
 * added line that goes on with a statement an unchanged line starts counts, however far up that line is: an
 * unchanged line gives a password name process.env.X, and the change adds a literal fallback lines below. It reads
 * back whatever the added line holds, a password named in its own literal, comment or environment name included,
 * when reading on from it finds none. The entries above a line, added or unchanged, also give the group its text
 * starts inside (outerAt): a field's declaration and a set call opened on a line above; and where the line starts inside
 * a string or a comment one of them opens, where the lexer stands there (leadOf).
 */
function readsFrom(handed, index, language) {
  const lines = ownLines(handed);
  const first = lines[index];
  if (!first || typeof first.text !== "string" || first.context) return false;
  if (typeof first.line !== "number") return namesPassword(first.text) && (holdsUnusualTerminator(first.text) || readsLiteral(first.text, language, "line") === true);
  return verdictsOf(lines, language)[index] === 1;
}

/** Per entries array a caller hands (markerHits'), the entries the readers read (ownLines). */
const OWN_LINES = new WeakMap();

/**
 * The entries `handed` as the readers read them, made once per array: each line's text without the CR of its CR LF
 * (lineText), whoever split it (changeset.mjs hands lines so; a diff's added lines and a caller's own split may keep
 * the CR). The array itself where no line ends in one, so every cache of the entries stays keyed by it. lineText
 * leaves the text it gives as it is (lineText(lineText(x)) === lineText(x)), so lines changeset.mjs has handed so stay
 * as they were handed, and a line whose own text ends in a CR keeps its CR LF's CR here too. An entry marked eof (a
 * file's last line, no LF after it: changeset.mjs) keeps a CR at its end, a bare CR.
 */
function ownLines(handed) {
  let lines = OWN_LINES.get(handed);
  if (lines) return lines;
  const ends = (entry) => typeof entry?.text === "string" && entry.eof !== true && lineText(entry.text) !== entry.text;
  lines = handed.some(ends) ? handed.map((entry) => (ends(entry) ? { ...entry, text: lineText(entry.text) } : entry)) : handed;
  OWN_LINES.set(handed, lines);
  return lines;
}

/**
 * Each added entry's verdict in `language` (1: it counts), read once: the first time readsFrom is asked about any
 * entry, every added entry with a line number is read, in order (readOnFrom, then readsBack), and each verdict kept.
 * READ_BACK_CHARS is spent by that one reading, so a verdict never depends on how often or in which order a caller
 * asks: markerHits and then stationReport over the same entries, or a test asking line by line, read the same.
 */
function verdictsOf(lines, language) {
  const state = entriesOf(lines);
  let verdicts = state.verdicts.get(language);
  if (verdicts) return verdicts;
  verdicts = new Uint8Array(lines.length);
  // A PHP file's page scripts are read as JavaScript (phpBlock's js), each line where it stands.
  const scripts = language === "php" ? verdictsOf(scriptLines(lines), "js") : null;
  for (let k = 0; k < lines.length; k++) {
    const entry = lines[k];
    if (typeof entry.text !== "string" || entry.context || typeof entry.line !== "number") continue;
    // Where PHP's tokenizer did not read the file, every line that names a password counts (phpFailure), with each piece
    // of PHP its text shows taken out too (namedOf).
    if (language === "php" && layoutOf(lines, k, LANGUAGES.php).php.failure) {
      if (namedOf(lines, language).own[k] === 1 || namesPassword(entry.text)) verdicts[k] = 1;
      continue;
    }
    if (outsidePlain(lines, k, language) || readOnFrom(lines, k, k, language) || readsBack(lines, k, language) || scripts?.[k] === 1) verdicts[k] = 1;
  }
  state.verdicts.set(language, verdicts);
  return verdicts;
}

/**
 * Read `lines[from]` on: through the entries before `added` whatever they are (the unchanged start of a
 * statement, at most STATEMENT_CHARS of them), and from there through up to WINDOW_LINES more entries and
 * WINDOW_CHARS of their characters. A statement still open where a limit stops the reading counts. Read from an
 * added line itself (from === added), the entries after it are read whatever they are: the unchanged lines below a
 * change (changeset.mjs addedWithContext hands them) are read through the end of the declaration or the statement
 * the line opens, and what they give it is the line's (a method's header renamed above its unchanged
 * body); where the entries end, what they hold has ended, as at a file's end. Where they stop short of it (cutAt: an
 * entry marked cut, after which the file goes on with lines not handed, a REST patch's few lines of context; or a gap
 * in the line numbers), what the line opens may go on unseen, and what is still open there counts, as at a limit.
 * Read back from an unchanged line above
 * (readsBack), only the run's added entries are read after `added`: the run goes on with a statement it does not
 * open, and the statement's own unchanged lines, above the run or below it, are not the change's.
 */
function readOnFrom(lines, from, added, language) {
  const more = (k) => followsOn(lines, k) && (from === added || k + 1 < added || !lines[k + 1].context);
  // What the text's end means after entry k: the next line is asked for where one follows, or where the lines stop
  // short of the file's end and the reading is the added line's own.
  const end = (k) => (more(k) || (from === added && cutAt(lines, k)) ? "more" : "change");
  const before = contextAt(lines, from);
  const alone = readsLiteral(lines[from].text, language, end(from), before);
  if (alone !== "more") return alone === true;
  let text = lines[from].text;
  // The change's own characters, and those of the lines above it.
  let own = from === added ? text.length : 0;
  let k = from;
  for (; k < added + WINDOW_LINES && more(k); k++) {
    const next = lines[k + 1].text;
    text += `${LINE_END}${next}`;
    if (k + 1 >= added) own += next.length + LINE_END.length;
    if (own > WINDOW_CHARS || text.length - own > STATEMENT_CHARS) return true;
  }
  // A read back past what the file's runs may read in all counts.
  if (from < added && !spend(lines, language, text.length)) return true;
  return readsLiteral(text, language, end(k), before) !== false;
}

/**
 * Whether the statement that `lines[k]`'s line starts, read on through the entries after it whatever they are, is
 * still open after entry `last` (in k's block): true, false (it ended, or holds a literal its own lines gave it), or
 * null when the lines from k to `last` run past STATEMENT_CHARS while it is open, or the reading runs past
 * READ_BACK_CHARS. It is read in windows that grow from one line, or from twice what is known open, so a statement
 * that ends soon costs a few lines, and what is learnt is kept per entry.
 */
function openThrough(lines, k, last, language) {
  const known = perLanguage(entriesOf(lines), "statements", language);
  let seen = known.get(k);
  if (!seen) known.set(k, (seen = { open: k - 1, ended: Infinity }));
  if (last <= seen.open) return true;
  if (last >= seen.ended) return false;
  const before = contextAt(lines, k);
  // A statement whose line's start the lexer cannot place (leadOf) may still be open: the run counts.
  if (Object.hasOwn(LANGUAGES, language) && before.lead(LANGUAGES[language]) === null) return null;
  for (let size = Math.max(1, 2 * (seen.open - k + 1)); ; size = 2 * size + WINDOW_LINES) {
    // The window, cut at the last line that keeps it within STATEMENT_CHARS (the statement's own line is always read).
    let text = lines[k].text;
    let w = k;
    while (w < Math.min(last, k + size - 1) && text.length + lines[w + 1].text.length + LINE_END.length <= STATEMENT_CHARS) text += `${LINE_END}${lines[++w].text}`;
    const cut = w < Math.min(last, k + size - 1);
    if (!spend(lines, language, text.length)) return null;
    if (readsLiteral(text, language, "more", before) !== "more") {
      seen.ended = Math.min(seen.ended, w);
      return false;
    }
    seen.open = Math.max(seen.open, w);
    if (w === last) return true;
    if (cut) return null;
  }
}

/**
 * The first added line of a run, read back over every line above it in its block, nearest first: a line that names
 * a password and starts a statement still open before the run (openThrough), read on through the run's lines,
 * counts when it gives the password a literal. A statement whose own unchanged lines hold the literal already is not
 * this change's. Where a limit stops the reader from finding the statement's start, the run counts: a statement
 * that is open past STATEMENT_CHARS, more than OPEN_STATEMENTS open around the run, or one that starts above the
 * first line markerHits was handed (startsAbove).
 */
function readsBack(lines, index, language) {
  // A later line of a run is read with the run, from its first line.
  if (index > 0 && followsOn(lines, index - 1) && !lines[index - 1].context) return false;
  const state = entriesOf(lines);
  const { top, mentions } = state;
  const ended = endedLinks(state, language, index);
  let open = 0;
  let m = mentions.length - 1;
  while (m >= 0 && mentions[m] >= index) m--;
  for (m = ended.alive(m); m >= 0 && mentions[m] >= top[index]; m = ended.alive(m - 1)) {
    const k = mentions[m];
    const verdict = openThrough(lines, k, index - 1, language);
    if (verdict === false) {
      ended.end(m);
      continue;
    }
    if (verdict === null || ++open > OPEN_STATEMENTS) return true;
    if (readOnFrom(lines, k, index, language)) return true;
  }
  return startsAbove(lines, index, language);
}

/**
 * Per language, the mentions whose statement ended above the runs read so far, linked past so that each run's read
 * back skips them at once: alive(m) is the nearest mention at or before m not known ended, end(m) links past m. A
 * statement that ended above one run ended above every later one. verdictsOf reads the runs in order; a read back
 * that comes before the last one would start the links afresh.
 */
function endedLinks(state, language, index) {
  let links = state.links.get(language);
  if (!links || index < links.index) {
    const link = Int32Array.from(state.mentions, (_, m) => m);
    links = {
      index,
      alive(m) {
        let root = m;
        while (root >= 0 && link[root] !== root) root = link[root];
        for (let k = m; k >= 0 && link[k] !== k; ) {
          const next = link[k];
          link[k] = root;
          k = next;
        }
        return root;
      },
      end(m) {
        link[m] = m - 1;
      },
    };
    state.links.set(language, links);
  }
  links.index = index;
  return links;
}

/**
 * Operators a line may start with and not go on with the line before it: a prefix, a doc comment's "*", and "<" and
 * ">", which start HTML in a PHP file and JSX in a script far more often than they compare.
 */
const LINE_STARTS = new Set(["!", "~", "@", "++", "--", "...", "*", "**", "&", "<", ">"]);
/** Joining words a statement cannot start with. */
const BETWEEN_WORDS = new Set(["and", "or", "xor", "instanceof", "in", "is", "as", "satisfies", "of"]);

/**
 * Whether a line that starts with token `k` goes on with the line before it, or stands inside a group opened above: a
 * ")" or "]", a comma, an operator no line starts with (LINE_STARTS), or a joining word no statement starts with. A
 * word, a string, a number, "(", "[" and "{" may start a statement, and a "}" ends a block.
 */
function goesOnAbove(r, k) {
  const t = r.toks[k];
  if ((t.k === "close" && t.v !== "}") || t.k === "comma") return true;
  if (t.k === "op") return !LINE_STARTS.has(t.v);
  return t.k === "word" && BETWEEN_WORDS.has(t.v.toLowerCase());
}

/** Words after which a statement still waits for its operand on the next line. */
const OPERAND_WORDS = new Set(["return", "yield", "echo", "print", "throw", "case", "else", "do"]);

/** Whether token `p` completes an operand, so that a line break after it may end the statement. */
function completes(r, p) {
  const t = r.toks[p];
  if (t.k === "str" || t.k === "num" || t.k === "regex" || t.k === "close") return true;
  if (t.k === "word") return !JOINING.has(t.v.toLowerCase()) && !OPERAND_WORDS.has(t.v.toLowerCase());
  return isOp(t, "++") || isOp(t, "--");
}

/**
 * Whether the text of a run of added lines holds a literal wherever its statement started: each operand, read as a
 * value (a reader's names and a subscript's keys are none) with the separators and operators between them passed
 * over, or a string the run leaves open. `lead`: where the run starts inside a string or a comment a line above
 * opens, the text that puts the lexer there (leadOf), and `prev`, the token before it (lexAfter).
 */
function addsLiteral(text, L, lead = "", prev = null, at = null, prev2 = null, tokens = null) {
  // A PHP run's tokens are its block's, PHP's own (phpSlice).
  const r = tokens ?? reader(text, L, 0, "change", null, lead, prev, { known: prev !== null, nl: aboveBreak(lead, prev), at, prev2 });
  if (r.toks.unsure >= 0 || r.toks.some((t) => t.k === "str" && !t.closed)) return true;
  for (let j = skip(r, 0, true); j < r.toks.length; j = skip(r, j, true)) {
    const t = r.toks[j];
    if (isGroupEnd(t) || (t.k === "op" && !PREFIX.has(t.v)) || (t.k === "word" && JOINING.has(t.v.toLowerCase()) && !STARTING.has(t.v.toLowerCase()))) {
      j++;
      continue;
    }
    const value = readValue(r, j, 0, { group: true });
    if (value.lit) return true;
    j = Math.max(value.end, j + 1);
  }
  return false;
}

/**
 * Whether the run of added lines from `lines[index]` adds a literal to a statement whose start the entries do not
 * hold: its block starts below the file's first line (a caller handed fewer lines, or a gap in them), the run holds
 * a literal (addsLiteral), and read back from the run's first token to the block's start no statement ends (a ";",
 * PHP's "?>", a line break after a complete operand before a line that does not go on, or a body's "{" around it),
 * while a bracket closes one that opens above the block, a comma stands at the block's top, or the block's first
 * token shows no statement starting there: it goes on with a line above (goesOnAbove), or it starts a bare value
 * (bareValue: a literal, or a name joined to an operand). Another word (a statement's keyword, an assignment, a
 * call), a key, "{", a block's "}" and an operator a line starts with (LINE_STARTS) may start one. A block read as
 * starting inside a comment starts its statement after the comment's close. PHP, JavaScript and Python; a text
 * file's value is read from its key.
 */
function startsAbove(lines, index, language) {
  if (!Object.hasOwn(LANGUAGES, language)) return false;
  const { top } = entriesOf(lines);
  if (startsText(lines[top[index]])) return false;
  const layout = layoutOf(lines, index, LANGUAGES[language]);
  const { b } = layout;
  const { toks } = b;
  let last = index;
  while (followsOn(lines, last) && !lines[last + 1].context) last++;
  const from = layout.offset[index - layout.start];
  const to = last < layout.end ? layout.offset[last + 1 - layout.start] : b.text.length;
  let f = 0;
  while (f < toks.length && toks[f].e <= from) f++;
  f = skip(b, f, true);
  if (f >= toks.length || toks[f].s >= to) return false;
  // A run that starts inside a string or a comment a line above opens is lexed from there (leadOf); one whose start
  // the lexer cannot place adds what it holds.
  const lead = toks[f].s < from ? layout.leads[index - layout.start] : "";
  const at = Math.max(from, toks[f].s);
  const php = b.L.id === "php" ? phpSlice(b, at, to, 0, "change") : null;
  if (lead !== null && !addsLiteral(b.text.slice(at, to), b.L, lead, layout.prevs[index - layout.start], php ? null : blockTokens(b, at, lead.length), layout.prev2s[index - layout.start], php)) return false;
  let first = f;
  for (let j = f - 1; j >= 0; j--) {
    const t = toks[j];
    // A block read as starting inside a comment (layoutOf): what follows the comment's close starts a statement.
    if (t.k === "comment" && t.s === 0 && layout.lead) return false;
    if (t.k === "ws" || t.k === "comment") continue;
    if (t.k === "nl") {
      const p = back(b, j);
      if (p < 0) break;
      if (completes(b, p) && !goesOnAbove(b, first)) return false;
      j = p + 1;
      continue;
    }
    if (t.k === "semi" || t.k === "end") return false;
    if (t.k === "close") {
      if (b.opener[j] < 0) return true;
      j = b.opener[j];
      first = j;
      continue;
    }
    if (t.k === "open" && t.v === "{" && ownDeclaration(b, j).bound) return false;
    if (t.k === "comma" && b.parent[j] < 0) return true;
    first = j;
  }
  // The block's top, with no boundary between it and the run: its first token must show a statement or an item
  // starting there. One that goes on with a line above does not, and nor does a bare value (bareValue).
  return goesOnAbove(b, first) || bareValue(b, first);
}

/** Words that start a statement, or stand before an operand: no bare value starts with one. */
const STATEMENT_WORDS = new Set([
  ...STARTING,
  ...DECLARING,
  "return", "echo", "print", "throw", "if", "else", "elseif", "elif", "for", "foreach", "while", "do", "switch", "case",
  "default", "break", "continue", "try", "catch", "finally", "except", "function", "fn", "class", "interface", "enum",
  "def", "import", "export", "from", "use", "namespace", "global", "include", "include_once", "require",
  "require_once", "unset", "list", "goto", "with", "assert", "del", "pass", "raise", "yield", "abstract", "final",
]);

/** Operators that join an operand to the next and are no assignment, call, member access or update ("<" and ">" start HTML). */
const BINARY = new Set(["+", "-", "*", "/", "%", "**", "//", "??", "||", "&&", "|", "&", "^", "<<", ">>", ">>>", "<=", ">=", "==", "===", "!=", "!==", "<>", "<=>", "?", ":"]);

/**
 * Whether token `k`, at the top of the lines a reader was handed, starts a bare value: one that is no statement worth
 * writing, so it may be the value of a statement above them (a password's declaration with its "=" at its line's end).
 * A bare literal: a string, a number or a regular expression that is no key ("k" => v, a "k": v with its ":" glued to
 * it, "k"?: T), or "(" or "[" around one that is no computed key or name. A bare expression: a name, with its members
 * and subscripts, joined to what follows by an operator that is no assignment, call, member access or update, or by
 * a joining word (x ?? '...', $a . '...', x or '...').
 */
function bareValue(r, k, depth = 0) {
  const { toks, match, L } = r;
  const t = toks[k];
  if (!t || depth > READ_DEPTH) return false;
  if (t.k === "str" || t.k === "num" || t.k === "regex") {
    let n = skip(r, k + 1);
    let end = t.e;
    if ((isOp(toks[n], "?") || isOp(toks[n], "!")) && toks[n].s === end) {
      end = toks[n].e;
      n = skip(r, n + 1);
    }
    if (isOp(toks[n], "=>")) return false;
    return !(isOp(toks[n], ":") && toks[n].s === end);
  }
  if (t.k === "open" && t.v !== "{") {
    // A group around a bare literal, unless it is a computed key or name ([...]: v, [...]?: T, [...]() {...}, [...] = v).
    const after = match[k] < 0 ? null : toks[skip(r, match[k] + 1)];
    const named = after && (isOp(after, ":") || isOp(after, "=>") || isOp(after, "<") || (after.k === "op" && ASSIGN.has(after.v)) || (after.k === "open" && after.v === "(") || ((isOp(after, "?") || isOp(after, "!")) && isOp(toks[skip(r, skip(r, match[k] + 1) + 1)], ":")));
    return !named && bareValue(r, skip(r, k + 1, true), depth + 1);
  }
  if (t.k !== "word" || STATEMENT_WORDS.has(t.v.toLowerCase())) return false;
  // The name's members and subscripts: PHP's ->, ?-> and ::, and JavaScript's and Python's "." and "?.".
  const member = L.id === "php" ? ["->", "?->", "::"] : [".", "?."];
  let n = skip(r, k + 1);
  for (;;) {
    if (toks[n]?.k === "op" && member.includes(toks[n].v) && toks[skip(r, n + 1)]?.k === "word") n = skip(r, skip(r, n + 1) + 1);
    else if (toks[n]?.k === "open" && toks[n].v === "[" && match[n] > 0) n = skip(r, match[n] + 1);
    else break;
  }
  const u = toks[n];
  if (!u) return false;
  if (u.k === "word") return JOINING.has(u.v.toLowerCase()) && !STARTING.has(u.v.toLowerCase());
  // PHP's "." joins strings. A ":" glued to a name is a key's or a label's, and so is one after TypeScript's glued
  // optional or definite marker (password?: T).
  if (isOp(u, ".") && L.id === "php") return true;
  const glued = u.s === toks[back(r, n, false)].e;
  if (isOp(u, ":") && glued) return false;
  if ((isOp(u, "?") || isOp(u, "!")) && glued && isOp(toks[n + 1], ":")) return false;
  return u.k === "op" && BINARY.has(u.v);
}

/** The shape a reader that can only rebuild a regular expression from a spelling's source reads: any value, fail closed. */
const PASSWORD_SHAPE = /pass(?:word|wd)\w*['"]?\s*\]?\s*(?:=(?![=>])|=>|:(?!:))/i;

/**
 * A marker spelling read by the tokenizer, shaped as a regular expression: exec() and test() read
 * one line; the spelling's readsFrom() reads the change's lines from one on.
 */
class PasswordPattern extends RegExp {
  static get [Symbol.species]() {
    return RegExp;
  }
  constructor(language) {
    super(PASSWORD_SHAPE.source, PASSWORD_SHAPE.flags);
    Object.defineProperty(this, "language", { value: language });
  }
  exec(text) {
    const s = String(text);
    const line = lineText(s);
    // Outside the plain subset a line that names a password counts (holdsUnusualTerminator); inside it the reader decides.
    const hit = namesPassword(line) && (holdsUnusualTerminator(line) || readsLiteral(line, this.language, "line") === true);
    return hit ? Object.assign([s], { index: 0, input: s }) : null;
  }
}

/** The password literal's spelling for `language`, read in the files `files` names (all files without), but those `notIn` names. */
function passwordSpelling(language, files, notIn) {
  const spelling = { marker: "password literal", pattern: new PasswordPattern(language), readsFrom: (lines, index) => readsFrom(lines, index, language) };
  return { ...spelling, ...(files ? { in: files } : {}), ...(notIn ? { notIn } : {}) };
}

/** PHP files. */
export const PHP_FILES = ["*.php", "*.inc"];
/** JavaScript and TypeScript files. */
export const JS_FILES = ["*.js", "*.mjs", "*.cjs", "*.jsx", "*.ts", "*.mts", "*.cts", "*.tsx"];
/** Python files. */
export const PYTHON_FILES = ["*.py"];
/** Files whose ordinary quotes never interpolate: JavaScript, TypeScript and Python. */
export const LITERAL_QUOTE_FILES = [...JS_FILES, ...PYTHON_FILES];
/** YAML files: a key given nothing on its line takes the more-indented lines after it. */
export const YAML_FILES = ["*.yml", "*.yaml"];
/** Shell, environment, YAML, configuration and prose files, where an unquoted value is text, not code. */
export const TEXT_FILES = [
  "*.sh",
  "*.bash",
  "*.env",
  "*.env.*",
  "*.yml",
  "*.yaml",
  "*.ini",
  "*.conf",
  "*.cfg",
  "*.toml",
  "*.properties",
  "*Dockerfile*",
  "*.service",
  "*.md",
  "*.txt",
];

// Every file but a PHP file is read by the JavaScript, Python and text readers, and counts where all of them
// count: a file whose language the map does not know. JavaScript and TypeScript, Python, TEXT_FILES and
// YAML_FILES are also read by their own reader, which only adds hits. A PHP file is read by PHP's own tokens
// alone (phpBlock), and where PHP's tokenizer cannot read it, a line of it that names a password counts.
const PASSWORD_SPELLINGS = [
  passwordSpelling("any", null, PHP_FILES),
  passwordSpelling("php", PHP_FILES),
  passwordSpelling("js", JS_FILES),
  passwordSpelling("py", PYTHON_FILES),
  passwordSpelling("text", TEXT_FILES),
  passwordSpelling("yaml", YAML_FILES),
];

/** The one-way rows, in party order (1b, 1a, 1c, 1d). */
export const ONE_WAY_ROWS = [
  {
    id: "payments",
    door: "one-way",
    station: "envejsdorene",
    party: "1b",
    reviewer: "Nicolai",
    tier: TIERS.payments,
    why: "a charge, refund, terminal sale, PBS collection or bank payment file happens outside Saldi, and a revert refunds nothing",
    examples: ["#577", "#768", "#771", "#772", "#980"],
    paths: [
      "api/stripe/",
      "includes/stripeIncludes/",
      "admin/stripe_setup.php",
      "tools/stripe_*",
      "systemdata/diverseIncludes/{stripeValg,create_vibrant_*,show_vibrant_*,save_flatpay_*}",
      "debitor/payments/",
      "{mysale,remoteBooking}/vibrantPayment*",
      "includes/formFuncIncludes/paymentLink.php",
      "api/quickpay/",
      "api/{callback,kortbetaling,pbs_tilmelding}.php",
      "{debitor,finans}/pbs*",
      "includes/pbsfunc.php",
      "rotary/pbs_*",
      "{debitor,kreditor}/{betalinger,betalingsliste}.php",
      "debitor/pos_ordre_includes/{paymentFunc,voucherFunc}/",
      "includes/stdFunc/mobilepayWebhookSync.php",
    ],
    markers: ["pos_betalinger", "stripe_events", "betalingsliste"].map((table) => ({
      marker: `write to ${table}`,
      pattern: new RegExp(`\\b(insert\\s+into|update|delete\\s+from)\\s+${table}\\b`, "i"),
    })),
    proof: { text: "Test mode only (Stripe test keys, a sandbox endpoint), with the request shown.", e2e: "flow" },
  },
  {
    id: "outbound",
    door: "one-way",
    station: "envejsdorene",
    party: "1b",
    reviewer: "Nicolai",
    tier: TIERS.outbound,
    why: "the recipient already has the mail, reminder, SMS, e-invoice or shop update; a stock or price push is live in the shop",
    examples: ["#583", "#881", "#885", "#955", "#970"],
    paths: [
      "includes/formFuncIncludes/{sendMail,oldSendMail}.php",
      "debitor/{mail_*,rykkertjek,inkassoprint,commissionMail}.php",
      "kreditor/mail_*.php",
      "{mysale,remoteBooking}/{sendMail,new-email,new-mail,mail}.php",
      "api/send_invoice_pdf.php",
      "lager/lagerstatusmail.php",
      "debitor/{api,easyUBL,peppol,oioubl_dok}.php",
      "includes/{,topmenu/}oioublfunk.php",
      "includes/stdFunc/{easyUblResponse,einvoice*}.php",
      "index/{createInEasyUbl,askNemhandel}.php",
      "includes/stdFunc/shopApiRequest.php",
      "sync_shop/",
      "api/{varesync,varesync_pos8,updateShopCostPrice}.php",
      "lager/webshopStock.php",
      "includes/shopify/",
      "api/shopify/",
    ],
    markers: [
      { marker: "new PHPMailer", pattern: /\bnew\s+PHPMailer\b/i },
      { marker: "send_sms(", pattern: /\bsend_sms\s*\(/i },
      { marker: "shopApiRequest(", pattern: /\bshopApiRequest\s*\(/ },
    ],
    proof: { text: "Test mode only (mail caught in Mailpit, a sandbox endpoint), with the mail or request shown.", e2e: "flow" },
  },
  {
    id: "posting",
    door: "one-way",
    station: "envejsdorene",
    party: "1a",
    reviewer: "Nicolai",
    tier: TIERS.books,
    why: "rows in transaktioner and openpost stay after a revert, and a VAT return or a closed period may already use them",
    examples: ["#831", "#884", "#942"],
    paths: [
      "finans/bogfor.php",
      "kreditor/bogfor.php",
      "includes/ordrefunc.php",
      "includes/openpost.php",
      "includes/udlign_openpost.php",
      "includes/genberegn.php",
      "includes/orderFuncIncludes/genberegn.php",
      "includes/alignOpenpostIncludes/",
      "includes/ret_valutadiff.php",
      "includes/payListFunc.php",
      "finans/autoudlign.php",
      "finans/kassekladde_includes/autoSettlement.php",
      "debitor/pos_ordre_includes/{cashInventory,settleCommission}/",
      "systemdata/{valutakort,laas_lager}.php",
      "systemdata/*/createAccountPrimo.php",
      "admin/verify_moms_periode_luk.php",
    ],
    markers: [
      { marker: "insert into transaktioner", pattern: /\binsert\s+into\s+transaktioner\b/i },
      { marker: "insert into openpost", pattern: /\binsert\s+into\s+openpost\b/i },
      { marker: "update openpost set", pattern: /\bupdate\s+openpost\s+set\b/i },
    ],
    proof: BOOKS_PROOF,
  },
  {
    id: "stock",
    door: "one-way",
    station: "envejsdorene",
    party: "1a",
    reviewer: "Nicolai",
    tier: TIERS.books,
    why: "stock moves stay after a revert and feed stock value and cost of sales; goods receipt also pushes stock to the shop",
    examples: ["#835", "#864"],
    paths: [
      "lager/{modtagelse,lagerflyt,lagerregulering,optalling,vareproduktion,batch_udfyld}.php",
      "kreditor/modtag.php",
      "kreditor/orderIncludes/moveOrderLines.php",
    ],
    markers: [],
    proof: BOOKS_PROOF,
  },
  {
    id: "numbers",
    door: "one-way",
    station: "envejsdorene",
    party: "1a",
    reviewer: "Nicolai",
    tier: TIERS.books,
    why: "an issued invoice number is on a sent invoice and in the books; a duplicate or a gap cannot be withdrawn",
    examples: ["#894"],
    paths: ["debitor/pos_ordre_includes/exitFunc/", "debitor/pos_ordre_includes/report/saveReport.php"],
    markers: [{ marker: "get_next_invoice_number(", pattern: /\bget_next_invoice_number\s*\(/i }],
    proof: BOOKS_PROOF,
  },
  {
    id: "schema",
    door: "one-way",
    station: "envejsdorene",
    party: "1c",
    reviewer: "Nicolai",
    tier: TIERS.structure,
    why: "an update step runs once per tenant at login; a revert does not drop a column, undo an UPDATE or unset the flag that stops it running again",
    examples: ["#795", "#832", "#927"],
    paths: [
      "includes/betweenUpdates.php",
      "includes/opdat_*.php",
      "includes/opdat_func/",
      "includes/opdater.php",
      "includes/tjek4opdat.php",
      "includes/stdFunc/ensureTableAndColumns.php",
      "index/install.php",
      "admin/opret.php",
      "includes/opretfunc.php",
    ],
    // An update step that changes data, not only structure, takes the deep tier.
    markersIn: "row-paths",
    markerTier: TIERS.dataStep,
    markers: [
      { marker: "update <table> set", pattern: /\bupdate\s+[\w."`]+\s+set\b/i },
      { marker: "delete from", pattern: /\bdelete\s+from\b/i },
    ],
    proof: { text: "The step run twice on a copied tenant, on Postgres and on MySQL or MariaDB (CI has no MySQL), then a login.", e2e: "pr" },
  },
  {
    id: "auth",
    door: "one-way",
    station: "envejsdorene",
    party: "1d",
    reviewer: "Nicolai",
    tier: TIERS.access,
    why: "data shown to another tenant or user cannot be unseen, and a leaked token works until it is rotated",
    examples: ["#778", "#780", "#967"],
    paths: [
      "includes/{online,login,db_query,publicTenantDb,locatorClient,glemt_kode}.php",
      "index/{login,logud,glemt_kode}.php",
      "restapi/core/{auth,JWT,JWTAuth,JwtSecretProvisioning}.php",
      "api/v2/includes/{AuthMiddleware,ApiKeyManager}.php",
      "mysale/getDB.php",
      "admin/{aaben_regnskab,admin_brugere,admin_panel,vis_regnskaber}.php",
    ],
    markers: [],
    proof: { text: "A test for the denied case and for an empty grant list.", e2e: "none" },
  },
  {
    id: "destructive",
    door: "one-way",
    station: "envejsdorene",
    party: "1d",
    reviewer: "Nicolai",
    tier: TIERS.access,
    why: "deleted rows and files are gone; a restore is per tenant, by hand, and loses everything after the backup",
    examples: ["#731", "#831", "#916"],
    paths: [
      "admin/{slet_regnskab,restore}.php",
      "systemdata/resetAccount.php",
      "systemdata/financialYearInc/deleteFinancialYear.php",
      "systemdata/fiscalYearInc/deleteFiscalYear.php",
      "{includes/,}docsIncludes/{deleteDoc,poolArchive}.php",
      "api/restApiIncludes/deleteOrder.php",
      "kreditor/scanAttachments/frontpage/deleteOrder.php",
      "lager/slet_shopbinding.php",
    ],
    markers: [
      ...TRUNCATE_MARKERS.map((pattern) => ({ marker: "TRUNCATE", pattern })),
      { marker: "DROP DATABASE", pattern: /\bdrop\s+database\b/i },
      { marker: "DROP SCHEMA", pattern: /\bdrop\s+schema\b/i },
      ...[
        "transaktioner",
        "openpost",
        "ordrer",
        "ordrelinjer",
        "adresser",
        "kassekladde",
        "varer",
        "batch_kob",
        "batch_salg",
        "lagerstatus",
        "pool_files",
        "documents",
      ].map((table) => ({ marker: `delete from ${table}`, pattern: new RegExp(`\\bdelete\\s+from\\s+${table}\\b`, "i") })),
    ],
    proof: { text: "What is deleted or run, shown on a disposable copy.", e2e: "none" },
  },
  {
    id: "deploy",
    door: "one-way",
    station: "envejsdorene",
    party: "1d",
    reviewer: "Nicolai",
    tier: TIERS.access,
    why: "a deploy workflow runs shell on the production server, and a workflow with secrets runs on a public repo; what it ran or printed stays",
    examples: ["#935"],
    paths: [".github/workflows/deploy-*.yml"],
    markersIn: [".github/"],
    markers: [
      { marker: "pull_request_target", pattern: /\bpull_request_target\b/ },
      { marker: "secrets.", pattern: /\bsecrets\.[A-Za-z_]/ },
      { marker: "self-hosted", pattern: /\bself-hosted\b/ },
    ],
    proof: { text: "What is deleted or run, shown on a disposable copy.", e2e: "none" },
  },
  {
    id: "secrets",
    door: "one-way",
    station: "envejsdorene",
    party: "1d",
    reviewer: "Nicolai",
    tier: TIERS.access,
    why: "the repository is public, so a pushed secret is public before review and stays in history and forks; only rotation helps",
    examples: ["#768"],
    paths: [],
    // A key is a key in any file, a comment or a test: only vendored code is skipped.
    markersEverywhere: true,
    markers: [
      { marker: "sk_live_ key", pattern: /\b[sr]k_live_[0-9A-Za-z]{10,}/ },
      { marker: "whsec_ key", pattern: /\bwhsec_[0-9A-Za-z]{10,}/ },
      { marker: "PRIVATE KEY block", pattern: /-----BEGIN [A-Z ]*PRIVATE KEY-----/ },
      ...PASSWORD_SPELLINGS,
    ],
    // A push leaks it before any PR exists; it belongs in its own change check, not in the PR description.
    crossCheck: false,
    proof: { text: "None: nothing secret in the repo; rotate first if it happened.", e2e: "none" },
  },
];

/** Rows that make the whole order human-only: never cooked by an agent. */
export const HUMAN_ONLY_ROWS = ["deploy", "secrets"];

/** The care rows: the check never fails on them; they tell the author what blast radius to write. */
export const CARE_ROWS = [
  {
    id: "order-pages",
    door: "care",
    station: "faellesgryden",
    reviewer: "Nicolai",
    tier: TIERS.care,
    why: "bogfor() posts the totals and VAT the order page saved, so a change to totals, VAT or invoice date reaches the books at the next invoice",
    examples: ["#917", "#966"],
    paths: ["debitor/ordre.php", "kreditor/ordre.php", "debitor/orderIncludes/"],
  },
  {
    id: "kassekladde",
    door: "care",
    station: "faellesgryden",
    reviewer: "Saul",
    tier: TIERS.care,
    why: "draft rows are not posted yet, but a bug can overwrite or drop what the bookkeeper typed, and voucher numbers are posted at Bogfør",
    examples: ["#824", "#848", "#865"],
    paths: ["finans/kassekladde.php", "finans/kassekladde_includes/"],
    except: ["finans/kassekladde_includes/autoSettlement.php"],
  },
  {
    id: "shared-includes",
    door: "care",
    station: "faellesgryden",
    reviewer: "Nicolai",
    tier: TIERS.care,
    why: "loaded on every page in every tenant: a fatal locks everyone out until the revert deploys",
    examples: ["#855", "#878"],
    paths: ["includes/std_func.php", "includes/stdFunc/", "includes/settings.php", "includes/{dkdecimal,usdecimal}.php"],
  },
  {
    id: "shop-api-writes",
    door: "care",
    station: "faellesgryden",
    reviewer: "Nicolai",
    tier: TIERS.care,
    why: "creates orders, debtors and products from shops; wrong rows need a clean-up, but nothing leaves Saldi until invoiced",
    examples: ["#861", "#906", "#907"],
    paths: ["api/rest_api.php", "api/hent_ordrer.php", "restapi/endpoints/", "restapi/models/"],
  },
  {
    id: "print-mail-engine",
    door: "care",
    station: "faellesgryden",
    reviewer: "Saul",
    tier: TIERS.care,
    why: "the print engine plus send_mails(): a layout change is two-way, a change to what is mailed is one-way",
    examples: ["#782", "#820"],
    paths: ["includes/formfunk.php", "debitor/formprintfunc.php"],
  },
  {
    id: "vat-saft",
    door: "care",
    station: "faellesgryden",
    reviewer: "Nicolai",
    tier: TIERS.care,
    why: "read-only in Saldi, but the VAT return is filed from it and SAF-T files go to the authorities",
    examples: [],
    paths: ["finans/rapport_includes/momsangivelse.php", "finans/saft*.php", "debitor/saftCashRegister*.php"],
  },
  {
    id: "texts",
    door: "care",
    station: "skaerm-og-tekst",
    reviewer: "Saul",
    tier: null,
    why: "texts are cached in each tenant's tekster table on first use, so a revert does not fix a cached wrong text",
    examples: ["#957"],
    paths: ["importfiler/tekster*.csv", "importfiler/*_keyed.tsv"],
    proof: { text: "The tekster guardrail passes for the text rows.", e2e: "none" },
  },
  {
    id: "ci",
    door: "care",
    station: "gartneren",
    reviewer: "Nicolai",
    tier: TIERS.gate,
    why: "a revert restores CI, but a check that silently stops running hides regressions",
    examples: ["#913", "#936", "#950"],
    paths: [".github/workflows/*", "tools/ci/", "Dockerfile", "docker-entrypoint.sh", "phpunit.xml"],
    except: [".github/workflows/deploy-*.yml"],
  },
];
