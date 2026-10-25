# VU KNF Lab

A hands-on cybersecurity lab for the students of Vilnius University Kaunas Faculty. Players
solve capture-the-flag challenges in cryptography, forensics, steganography, OSINT, web,
network security and reverse engineering, submit the flags they find, and climb the
leaderboard. Teachers run their own labs inside it, with private challenges for their groups
and a leaderboard for each group.

The live lab is at [lab.knf.vu.lt](https://lab.knf.vu.lt).

<br/>

## How a lab plays out

**Players** sign in with their VU account, or register with an e-mail address and confirm it
from the mail they receive. The dashboard lists the open challenges, which they can filter by
category and points. Each challenge comes with a task, sometimes files to download, and a
flag hidden somewhere, which the player types as `VU{...}`. A right answer
books the points at once: the leaderboard and the feed of recent solves in the sidebar update,
and the player can give the challenge a thumbs up or down. Players with equal points are
ranked by who got there first.

**Teachers** write challenges that stay off the main dashboard, put them into a group (a lab)
together with their students' VU ids, and follow the group's own leaderboard. Only the
group's members can see and open those challenges, on the group's page.

**Main admins** look after the rest: which challenges are public, the categories, the partners
credited on challenges, the content pages, the accounts, and whether registration is open.

<br/>

## Run it yourself

You need a Linux server with Docker and docker-compose, port 80 free, and a domain with an
HTTPS proxy in front of it. The lab answers plain HTTP on port 80 but builds its links with
`https://`, so it expects a proxy to add the HTTPS, as the faculty's does for lab.knf.vu.lt.

```bash
git clone https://github.com/kaunofakultetas/lab.knf.vu.lt.git
cd lab.knf.vu.lt

cp docker-compose.sample.yml docker-compose.yml   # then set APP_URL and MAIL_FROM_ADDRESS
cp .env.sample .env                               # then fill it in, see below

./runUpdateThisStack.sh
```

The script creates the data, log and SSO folders and starts everything. On the first start
MySQL builds the database from `mysql/init.sql`, so give it a minute.

MySQL reads that file only into an empty database. A lab set up before the votes moved into
MySQL (October 2026) needs their table before it runs the new image, or the dashboard fails:
run the ``CREATE TABLE `cyber_challenges_votes` `` statement of `mysql/init.sql` once, in
DbGate at `/dbgate`.

What goes into `.env`:

| Setting | What to put there |
|---|---|
| `LARAVEL_APP_KEY` | a fresh key: `echo "base64:$(openssl rand -base64 32)"` |
| `MYSQL_DATABASE`, `MYSQL_USER` | names of your choice, such as `lab` |
| `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `DBGATE_PASSWORD` | long random strings: `openssl rand -hex 24` |
| `RECAPTCHAV3_SITEKEY`, `RECAPTCHAV3_SECRET` | [reCAPTCHA v3](https://www.google.com/recaptcha/admin) keys for your domain, which the registration and password reminder forms need |
| `MAILJET_APIKEY`, `MAILJET_APISECRET` | your [Mailjet](https://www.mailjet.com) SMTP login, for the verification and password mails |

`NOCAPTCHA_*` and `IPGEO_API_KEY` are not used and can stay empty. Another SMTP server works
too: set `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME` and `MAIL_PASSWORD` on the
`vuknflab-apache` service in `docker-compose.yml`. The lab sends no mail in clear, so the
server must offer TLS. Prefer TLS from the start, on port 465, which the lab uses with
Mailjet: on 587 the mail waits for STARTTLS, and someone on the way who makes the server's
answer to `EHLO` fail gets it in clear, as the mailer then falls back to `HELO`. For a relay
that offers no TLS at all, set `MAIL_REQUIRE_TLS` to `false` there as well, or every mail fails.

### Signing in as an admin

The control panel at `/control-panel` has no password login: admins sign in through SAML
single sign-on, the way VU staff do with VU's identity provider. To use your own:

1. Put your [SimpleSAMLphp](https://simplesamlphp.org) service provider files under
   `_SSO/simplesaml/`: `config/`, `metadata/` with your identity provider, and `cert/`. The
   service provider is called `default-sp`, and the identity provider should release the
   `uid`, `mail`, `givenName` and `sn` attributes. In its `config.php`, set
   `session.phpsession.savepath` to `/var/www/simplesamlphp-sessions`, the folder the sample
   keeps across redeploys, and `session.duration` to `36 * 60 * 60`, as long as a lab session:
   signing out ends the single sign-on at your identity provider only while SimpleSAMLphp
   still holds its session. Set `'showerrors' => false` there as well, or its error pages
   show visitors a stack trace with the server's file paths, and `'logging.handler'` to
   `'errorlog'`, which writes the errors to Apache's error log instead: the container has
   no syslog. The image runs SimpleSAMLphp 2.5, which sends no NameID policy unless asked:
   to request a transient NameID, as earlier versions did, add
   `'NameIDPolicy' => ['Format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient']`
   to `default-sp` in `authsources.php`. The lab reads only the attributes. Add
   `'enable_unsolicited' => false` there too: the lab only takes logins it asked for, and
   SimpleSAMLphp otherwise also accepts one the identity provider starts on its own, which a
   link could use to sign a visitor into someone else's account.
2. Allow your identity provider's address in `endpoint/Caddyfile`, where `https://sso.vu.lt`
   is now.
3. Make yourself an admin. The database starts with one main admin whose uid is `admin`;
   change it to your own uid in the `cyber_admins` table, for example in DbGate at `/dbgate`
   (user `admin`, with the `DBGATE_PASSWORD` from `.env`).

Players can use the same sign-on, or register with an e-mail address.

<br/>

## What's inside

| Service | What it does |
|---|---|
| `vuknflab-endpoint` | Caddy on port 80: the security headers, `/dbgate`, `/static`, and everything else to the app |
| `vuknflab-apache` | the lab itself, a Laravel 13 app on PHP 8.4 and Apache, published as [`vuknf/vuknflab-apache`](https://hub.docker.com/r/vuknf/vuknflab-apache) |
| `vuknflab-mysql` | MySQL, created from `mysql/init.sql` |
| `vuknflab-redis` | Redis: the sessions and the cached leaderboard |
| `vuknflab-static` | fonts, icons and two scripts for old browsers, served from the lab itself rather than a CDN; Bootstrap comes with the lab's own script bundles |
| `vuknflab-dbgate` | DbGate, a database browser at `/dbgate` |

The live lab also runs `vuknflab-exit`, which lets outgoing connections reach reCAPTCHA and
Mailjet's mail server, on port 465, only; the sample leaves it out.

<br/>

## Working on it

- **Build the image yourself.** In `docker-compose.yml`, comment out the
  `image: vuknf/vuknflab-apache:latest` line and uncomment the two self-built lines above it.
- **Run the tests.** `apache/runTests.sh` runs the whole suite, over 500 tests, in one
  throwaway container with its own MySQL and Redis and no network, so it never touches a
  running lab. `apache/app/tests/README.md` explains how the tests are organised.
- **Find the code.** The app lives in `apache/app`, the database schema in `mysql/init.sql`.
