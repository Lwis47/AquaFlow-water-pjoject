import math, struct, zlib, os

OUT = os.path.join(os.path.dirname(__file__), '..', 'assets', 'icons')

def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))

CYAN = (34, 211, 238)
MID = (2, 132, 199)
DEEP = (12, 74, 110)
WHITE = (255, 255, 255)
SKY = (14, 165, 233)
SKYDEEP = (3, 105, 161)
HIGHLIGHT = (186, 230, 253)

def tile_color(x, y):
    t = min(1.0, max(0.0, (x + y) / 124.0))
    return lerp(CYAN, MID, t / 0.55) if t < 0.55 else lerp(MID, DEEP, (t - 0.55) / 0.45)

def in_tile(x, y, radius=14.0):
    qx = abs(x - 32) - (32 - radius)
    qy = abs(y - 32) - (32 - radius)
    ax, ay = max(qx, 0.0), max(qy, 0.0)
    return math.hypot(ax, ay) + min(max(qx, qy), 0.0) - radius <= 0

def in_drop(x, y):
    if y < 11 or y > 51:
        return False
    if y <= 36.8:
        w = 14.0 * ((y - 11) / 25.8) ** 0.78
    else:
        w = math.sqrt(max(0.0, 196 - (y - 36.8) ** 2))
    return abs(x - 32) <= w

def wave_y(x):
    return 39.5 - 3.0 * math.sin(2 * math.pi * (x - 18.6) / 20.6)

def sample(x, y, radius):
    if not in_tile(x, y, radius):
        return (0, 0, 0, 0)
    r, g, b = tile_color(x, y)
    if in_drop(x, y):
        r, g, b = WHITE
        if y >= wave_y(x):
            t = min(1.0, max(0.0, (x - 18) / 28.0))
            r, g, b = lerp(SKY, SKYDEEP, t)
        if math.hypot(x - 26.5, y - 29.5) <= 2.6:
            r, g, b = HIGHLIGHT
    return (r, g, b, 255)

def render(size, radius=14.0):
    ss = 4 if size <= 48 else 2
    px = bytearray()
    step = 64.0 / (size * ss)
    for py in range(size):
        row = bytearray()
        row.append(0)
        for pxx in range(size):
            acc = [0, 0, 0, 0]
            for sy in range(ss):
                for sx in range(ss):
                    x = (pxx * ss + sx + 0.5) * step
                    y = (py * ss + sy + 0.5) * step
                    c = sample(x, y, radius)
                    for i in range(4):
                        acc[i] += c[i]
            n = ss * ss
            row.extend(v // n for v in acc)
        px.extend(row)
    return bytes(px)

def png(size, data):
    def chunk(tag, payload):
        c = struct.pack('>I', len(payload)) + tag + payload
        return c + struct.pack('>I', zlib.crc32(tag + payload) & 0xffffffff)
    ihdr = struct.pack('>IIBBBBB', size, size, 8, 6, 0, 0, 0)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', ihdr)
            + chunk(b'IDAT', zlib.compress(data, 9)) + chunk(b'IEND', b''))

def write(name, size, radius=14.0):
    blob = png(size, render(size, radius))
    with open(os.path.join(OUT, name), 'wb') as f:
        f.write(blob)
    return blob

os.makedirs(OUT, exist_ok=True)
blobs = {}
for s in (16, 32, 48):
    blobs[s] = write('favicon-%d.png' % s, s)
write('apple-touch-icon.png', 180, radius=0.0)
write('icon-192.png', 192)
write('icon-512.png', 512)

entries = b''
offset = 6 + 16 * len(blobs)
images = b''
for s, blob in blobs.items():
    dim = 0 if s >= 256 else s
    entries += struct.pack('<BBBBHHII', dim, dim, 0, 0, 1, 32, len(blob), offset)
    images += blob
    offset += len(blob)
ico = struct.pack('<HHH', 0, 1, len(blobs)) + entries + images
with open(os.path.join(OUT, 'favicon.ico'), 'wb') as f:
    f.write(ico)
print('icons written:', sorted(os.listdir(OUT)))