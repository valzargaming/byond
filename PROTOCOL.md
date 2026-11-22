# BYOND network protocols: public sources and technical notes

This page collects what has been published about how BYOND clients, servers and
the BYOND Hub talk to each other. It is a reference, not an official
specification: everything here comes from community reverse-engineering, open
source implementations and the Hub's public output. Gaps are marked **Unknown**.

Three separate protocols are involved:

| Protocol | Who uses it | How well it is documented |
| --- | --- | --- |
| [Topic](#1-topic-protocol) | `world.Export()`, server lists, bots, server managers | Fully. Several independent implementations agree. |
| [Client game protocol](#2-client-game-protocol) | Dream Seeker and the webclient joining a world | Partly. Framing, handshake and encryption are documented. Authentication and most messages are not. |
| [Hub reporting](#3-the-byond-hub) | DreamDaemon telling the Hub it is online | Not at all. Only the Hub's public output is known. |

## Sources

| Source | What it covers | Licence | Revision read |
| --- | --- | --- | --- |
| [20kdc/byond-data-docs](https://github.com/20kdc/byond-data-docs): `protocol/Protocol.md`, `protocol/WebClient.md`, `algorithms/RUNSUB.md` | Client framing, sequence numbers, handshake, RUNSUB encryption, some message types, webclient HTTP and WebSocket notes. The author marks it "INCOMPLETE". The same repository documents the DMB, DMI, RSC and SAV file formats. | CC0-1.0 | 2022-04-02 |
| [NGGJamie/byond-topics](https://github.com/NGGJamie/byond-topics): `documentation/protocol.txt`, Python and Go examples | Topic request and response layout, reversed by BobOfDoom. It builds on a 2005 BYOND forum post by Crispy (`secure.byond.com/forum/?post=276414`) and notes where the modern protocol differs from it. | see repo | master |
| [Cyberboss/Byond.TopicSender](https://github.com/Cyberboss/Byond.TopicSender) (NuGet `Byond.TopicSender`) | A production C# Topic client, used by [tgstation-server](https://github.com/tgstation/tgstation-server). It handles a big-endian length, chunked I/O, string and float replies, and the 65535-byte limit. | see repo | 59b0414 (8.0.1, 2023-12-17) |
| [OpenDreamProject/OpenDream](https://github.com/OpenDreamProject/OpenDream): `OpenDreamRuntime/DreamManager.Connections.cs` | A server-side Topic listener in an open-source DM runtime. It recognises an encrypted Topic header that it does not implement. | AGPL-3.0 | c03f746 (2026-11) |
| [ZackBleus/BYONDProxyServer](https://github.com/ZackBleus/BYONDProxyServer) | A packet proxy and decrypter for Dream Seeker, built on 20kdc's notes. It shows those notes are enough to decrypt live client traffic. | see repo | master |
| [SS13HUB/byond2json-py](https://github.com/SS13HUB/byond2json-py), [lmas/ss13.se](https://github.com/lmas/ss13.se) | Parsers for the Hub's `?format=text` listing. | see repos | master |
| BYOND Hub, `https://www.byond.com/games/<Author>/<Game>?format=text` | Live listing: game metadata, plus each world's URL, server version, status and connected player keys. | n/a | read 2026-11-22 |
| [DM Reference](https://www.byond.com/docs/ref/) | The official meaning of `world/Topic()`, `world.Export()`, `world.hub`, `world.hub_password`, `world.visibility`, `world.status` and `world.OpenPort()`. | n/a | current |

## 1. Topic protocol

A Topic is a one-shot request to a running world. It is what
`world.Export("byond://host:port?query")` sends, and what SS13 server lists,
Discord bots and server managers use to read status. It runs over **TCP on the
world's normal game port**. The client sends one frame, the world calls
`/world/Topic(T, Addr, Master, Keys)` and replies with one frame, and the
connection closes.

### Request

| Offset | Size | Value |
| --- | --- | --- |
| 0 | 2 | `00 83`: frame type `0x0083` (uint16, big-endian) |
| 2 | 2 | payload length (uint16, big-endian) = `5 + len(query) + 1` |
| 4 | 5 | `00 00 00 00 00` |
| 9 | n | the query bytes, starting with `?` (ASCII, or UTF-8 in Byond.TopicSender) |
| 9+n | 1 | `00` terminator |

Example `?ping`:

```
00 83 00 0B 00 00 00 00 00 3F 70 69 6E 67 00
```

The byond-topics notes describe the bytes at offsets 1 to 3 as "`0x83`, a zero
byte, then a one-byte length". That only holds for short queries. Byond.TopicSender
and OpenDream both read offsets 2 to 3 as one big-endian uint16, which also matches
the general frame header of the client protocol in section 2.

### Response

| Offset | Size | Value |
| --- | --- | --- |
| 0 | 2 | `00 83` |
| 2 | 2 | length of everything after these 4 bytes (uint16, big-endian) |
| 4 | 1 | type: `0x06` string, `0x2A` number, `0x00` null or no reply |
| 5 | rest | string: bytes ending in a NUL terminator. Number: float32, little-endian. |

Example: a `?ping` reply of 169 is `00 83 00 05 2A 00 00 29 43`.

Notes:

- The meaning of a query is entirely up to the game's `world/Topic()`. In SS13
  codebases `?ping` usually returns the player count, and `?status` returns
  game-defined key/value or JSON text. The engine attaches no meaning to them.
- A request and its reply each fit in 65535 bytes of payload. Byond.TopicSender
  rejects anything larger and reads the reply in chunks until the stated length
  has arrived.
- A reply can arrive in several TCP reads. Keep buffering until
  `4 + length` bytes are in.
- **Unknown:** OpenDream also checks for a request that begins with `00 15` and
  logs "Encrypted World Topic request is not implemented." No public source
  describes that format. A reasonable guess is that it is the encrypted form
  BYOND uses between its own servers.

### PHP example

```php
<?php
/** Send one Topic query to a BYOND world and return the decoded reply. */
function byond_topic(string $host, int $port, string $query, float $timeout = 3.0): string|float|null
{
    if ($query === '' || $query[0] !== '?') {
        $query = '?' . $query;
    }
    $payload = str_repeat("\0", 5) . $query . "\0";
    if (strlen($payload) > 0xFFFF) {
        throw new \LengthException('Topic too long');
    }
    $packet = "\x00\x83" . pack('n', strlen($payload)) . $payload;

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp === false) {
        throw new \RuntimeException("connect failed: $errstr");
    }
    stream_set_timeout($fp, (int) ceil($timeout));
    fwrite($fp, $packet);

    $header = stream_get_contents($fp, 4);
    if (strlen($header) < 4 || substr($header, 0, 2) !== "\x00\x83") {
        fclose($fp);
        throw new \UnexpectedValueException('not a Topic reply');
    }
    $length = unpack('n', substr($header, 2, 2))[1];
    $body = '';
    while (strlen($body) < $length && !feof($fp)) {
        $chunk = fread($fp, $length - strlen($body));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $body .= $chunk;
    }
    fclose($fp);
    if (strlen($body) < $length || $length < 1) {
        throw new \UnexpectedValueException('short Topic reply');
    }

    return match ($body[0]) {
        "\x06" => rtrim(substr($body, 1), "\0"),
        "\x2A" => unpack('g', substr($body, 1, 4))[1], // float32 little-endian
        default => null,
    };
}
```

## 2. Client game protocol

This is the protocol Dream Seeker uses to join and play. Everything in this
section comes from 20kdc's `Protocol.md`, `WebClient.md` and `RUNSUB.md`, which
were written against versions between 354 and 513. Check anything here against
the version you target.

### Transport

- TCP on the world port. One port carries Topic traffic, game clients and the
  webclient.
- **Webclient:** the same port also answers HTTP. Known paths are `/query`
  (JSON such as `{"url": "http://localhost:53927/play", "version": "513.1526"}`),
  `/play` (the client page) and `/res/*` (the distribution's `web` folder). A
  WebSocket on `/` carries the game. Its frames are just a big-endian uint16 type
  followed by data, with no sequence numbers and apparently no encryption.

### Framing

Types and lengths are big-endian. The documentation describes other fields as
"probably LE everywhere", so state the byte order explicitly for every field.
Strings end in a NUL byte and carry no length prefix.

```
[uint16 BE sequence]   client to server only, and not on the handshake
uint16 BE type
uint16 BE length
length bytes of data   (encrypted once the key is non-zero)
```

Send each frame in a **single write call**. The notes tie this to the TCP PSH
flag.

**Sequence numbers.** The client picks the first one in its handshake. Each next
number, using 32-bit arithmetic:

```
r = s * 0x43d4
r = r + (r / 0xFFF1) * 15      // integer division
s = r & 0xFFFF
if s == 0: s = 1
```

### Handshake

1. **Client to server, `0x0001`** (18 bytes, little-endian fields):
   `uint32 byondVersion`, `uint32 minVersion`, `uint32 encryptionKeyModified`,
   `uint16 firstSequenceNumber`, `uint32 byondMinorVersion`. The last field is
   missing in version 354. The version that added it is **unknown**.
   `encryptionKeyModified = key - (minVersion * 0x10000 + byondVersion)`.
2. **Server to client, `0x0001`** (60 bytes, already encrypted):
   `uint32 byondVersion`, `uint32 minVersion`, `uint8 isPermanentPort`,
   `uint8 dmbFlagsHasEx`, `uint8 unknown` (added in 433), padding,
   `uint32 addToEncryptionKey`, padding. The client adds `addToEncryptionKey` to
   its key.
   - First padding: keep reading uint32 values while
     `((v + 0x71bd632f) & 0x04008000) != 0`.
   - Second padding: keep reading uint32 values while
     `((v + 0x17db36e3) & 0x00402000) != 0`.
   - Both sides contribute to the key, so "neither a modified client nor a
     modified server can force a zero encryption key, only both".
3. The client sends `0x001a`, and the server replies with `0x003b`, then `0x00ef`,
   then `0x000e` (cache list). Some exchanges follow, then the server sends
   `0x0018` and a batch of `0x0011` verb registrations. At that point the client
   is in the game.

### Encryption (RUNSUB)

The state is a 32-bit key and an 8-bit running checksum, which is a wrapping sum
of the **plain** bytes. To encrypt, add this to each byte:

```
checksum + (key >> (checksum % 32))      // logical shift
```

Then update the checksum with the plain byte. The final checksum byte is appended
to the message unencrypted. To decrypt, subtract the same values in the same
order.

### Known message types

| Direction | Type | Name | Notes |
| --- | --- | --- | --- |
| C to S | `0x0000` | Quit | Apparently a single NUL byte. |
| C to S | `0x0001` | Handshake | See above. |
| C to S | `0x0002` | Invoke verb | Uses the verb index from `0x0011`. Example: `02 00 01 00 02 00 00 00 00 00` invokes index 1. |
| C to S | `0x001a` | Handshake step | Likely the authentication step. Its content is **unknown**. |
| S to C | `0x0001` | Handshake | See above. |
| S to C | `0x000e` | Cache list | The webclient expects 6 bytes. Format **unknown**. |
| S to C | `0x0011` | Register verb | `uint16 LE index`, name string, then trailing bytes that are **unknown**. |
| S to C | `0x0018` | Handshake step | Empty in tests. |
| S to C | `0x0026` | Atom appearance | Partly decoded: 4 bytes, a string, 6 bytes, a text character. |
| S to C | `0x0027` | Output text | A string, possibly followed by optional fields. |
| S to C | `0x003b`, `0x00ef` | Handshake steps | Content **unknown**. |
| S to C | `0x0072` | Server information | Content **unknown**. |
| S to C | `0x00e5` | Winset (webclient) | `uint16 LE controls`, then control records and skin class strings. |

### Unknown

- **Authentication.** Nobody has published how a client proves its BYOND key to
  a world, or whether the world checks it with the Hub. The webclient notes stop
  at `0x001a` and call it "presumably the fancy auth thing". A test against
  513.1526 ended there with `Connection broken by server (error code 9)`.
- Most message bodies: map and appearance updates, icons and resource transfer,
  sounds, browse/output controls and input.
- Changes in 514, 515 and 516.

## 3. The BYOND Hub

### What a world controls (DM Reference)

- `world.hub`: the Hub path to list under, for example `Author.GameName`.
- `world.hub_password`: lets a server list under a Hub entry it does not own.
  Keep it out of public logs and docs.
- `world.visibility`: whether the world is listed. DreamDaemon's `-public` and
  `-invisible` launch flags set this at startup. In practice, launching with
  `-invisible` keeps a world unlisted even if DM later sets `visibility = 1`.
- `world.status`: the status text shown in the listing.

### What the Hub publishes

`https://www.byond.com/games/<Author>/<Game>?format=text` returns a DM-style text
document. The game header contains `type`, `title`, `path`, `short_desc`,
`long_desc`, `author`, `version`, `banner`, `icon`, `small_icon`, `multi_player`,
`date`, `last_updated`, `last_played`, `listing`, `tags`, `fans`, `screenshots`
and `video`. Each online world then follows as:

```
world/N
	url = "byond://BYOND.world.<id>"
	server_version = 516
	status = "..."
	players = list("Key1","Key2", ...)
```

Some entries also carry a `host` field.

Observations from the live listing, 2026-11-22:

- **The player count is not a separate field.** The number the website shows is
  the length of `players`: the keys of the clients currently connected to that
  DreamDaemon. A world can only raise its count by having more real clients
  connected. It cannot report a number or keys of its own choosing from DM.
- `status` is cut at about **255 characters**. Several listed worlds stop
  mid-word or mid-link, so put the important text first.
- `url` is a Hub alias (`BYOND.world.<id>`), not `host:port`. The client resolves
  it through BYOND.
- byond2json notes that the text output sometimes leaves out the last few worlds.
  ss13.se warns that "bad hosts and owners can spoof a server's reported player
  count".

### Unknown

- The wire protocol DreamDaemon uses to register with the Hub and send its status
  and player keys, including how often it reports.
- How `hub_password` is checked.
- How `BYOND.world.<id>` aliases are given out and resolved to an address.

No public source covers any of these.

## 4. What this means in practice

- **Reading a server's status** is straightforward: use Topic (section 1). It is
  stable and widely implemented, and each game defines its own queries.
- **Writing a client or bot that joins as a player** is not currently possible
  from public information, because the authentication step is undocumented.
- **Changing what the Hub shows** beyond `world.status` and `world.visibility`
  would need engine support from BYOND. The Hub's player list is taken from the
  clients actually connected to the listed DreamDaemon.
