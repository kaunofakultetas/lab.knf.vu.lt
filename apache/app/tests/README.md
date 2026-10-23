# Apache regression tests

The PHPUnit suite of the Laravel app in `apache/app`. It runs only
through `apache/runTests.sh`, never against the live stack.

| Suite | Folder | What it covers |
|---|---|---|
| Unit | `tests/Unit` | Pure helpers, no application: place ordinals, "3 days ago", the client-address picker |
| Feature | `tests/Feature` | The app through its HTTP kernel: registration, login, password reset, VU SSO, challenges, voting, leaderboards, groups, pages, feedback, the whole control panel, the route table's guards, CSRF, mails, models, error pages |
| Http | `tests/Http` | The image's own Apache, started by `run.sh` with the production environment: document root, rewrites, pages free of PHP diagnostics, directory listing, `/upload`, CSRF outside PHPUnit, forged client addresses |
| Defects | `tests/Defects` | Open defects, each stating the wanted behaviour under a `LAB-nn` id (see below) |

## Running

From the repository root:

    apache/runTests.sh                                  # everything
    apache/runTests.sh --testsuite Feature              # one suite
    apache/runTests.sh tests/Feature/Admin/UsersTest.php
    apache/runTests.sh --filter registration

The script builds the production image from `apache/Dockerfile` under
its own tag, `vuknflab-apache-check`, then the test image on top of it
from `apache/Dockerfile.tests`: MySQL 8.0.36 and Redis 7.2.3 (the
production stack's servers, from the Debian builds of the official
images), PHPUnit and Mockery in `/opt/test-tools` — the app's `vendor`
stays exactly what ships — and the suite, which `apache/.dockerignore`
keeps out of the production image. It then runs one throwaway
container, removed when the run ends, shaped like the live one: no
network, a read-only root filesystem with the live container's tmpfs
mounts, user 1000.

Inside, `tests/container/run.sh` starts MySQL with the schema of
`mysql/init.sql` (mounted read-only), Redis and Apache, all in memory
and on loopback, exports the environment `docker-compose.yml` gives the
live app with throwaway values, and runs PHPUnit. reCAPTCHA, Mailjet
and the VU SSO are unreachable, so a test that forgets to fake one of
them fails instead of passing by luck. When PHPUnit fails, the tail of
the Apache, Laravel and MySQL logs is printed under its report. The
running `vuknflab-*` containers are never touched.

A run takes a little over a minute, most of it the image builds and
MySQL creating its schema.

## Conventions

- Every test starts from the database exactly as `mysql/init.sql`
  leaves it — two settings rows, the main admin with id 1, seven
  categories — inside a transaction that is rolled back afterwards, and
  from an empty Redis. Build what a test needs with the `make*` helpers
  of `tests/TestCase.php`; sign in with `signIn` and `signInAdmin`.
- Nothing may reach the network. reCAPTCHA is faked with
  `fakeRecaptcha`; mail with `Mail::fake()`; the SimpleSAMLphp and
  Mailjet clients, which the app builds itself, with Mockery overloads
  (see `SsoLoginTest`, `EmailSyncTest`).
- The player dashboard and the group dashboard declare a PHP function
  inside their controller actions, so a PHP process can render each of
  them only once — fine under Apache, fatal under PHPUnit. Tests that
  open either page, and tests that overload a class, run in separate
  processes (`#[RunTestsInSeparateProcesses]`) and open the page once.
  Laravel 10.6 leaves its error handlers in place after each test, and
  on PHP 8.4 that kills every isolated test without a word, so
  `TestCase::tearDown` takes them off — keep calling `parent::tearDown()`.
- Style: test files follow the house-comments skill for PHP — file
  header, class banner and a banner per test method, data provider and
  hook. Check them from the repository root with
  `python3 .claude/skills/house-comments/scripts/house_check_php84.py --new apache/app/tests`.
  The app runs PHP 8.4 (`php:8.4.27-apache`), which reports the locked
  Laravel 10 and Symfony 6 code — and the app's own `DateInterval::$w`
  in the three copies of `time_elapsed_string` — as deprecated. PHPUnit
  counts those deprecations under the run's report; they do not fail it.
- Races are pinned without real concurrency: the test listens to its own
  request's queries (`DB::listen`) or Redis commands and slips the other
  request's write in where it would land live — see
  `test_simultaneous_solves_keep_all_points` in `Feature/Play/ChallengeTest.php`.
- A defect found but not yet fixed gets its regression test up front in
  `tests/Defects` (or, for the web server, in `tests/Http`), stating the
  wanted behaviour inside `assertDefectStillOpen('LAB-nn', ...)`. While
  the defect is open the test is reported incomplete, so the run stays
  green and lists every open defect at its end. When a fix lands the
  test fails on purpose: move it into its home file, drop the id, and
  remove the "Known bug" note from its banner.
