/**
 * Spring tối thiểu theo apple-design: critically damped mặc định, có vận tốc nhận từ cử chỉ,
 * ngắt được giữa chừng và luôn chạy từ giá trị đang hiển thị (presentation value).
 *
 * Tham số được thiết kế theo cặp damping/response của Apple:
 *   duration ≈ response, bounce = (1 - dampingRatio). 0 = critically damped.
 */
export interface SpringOptions {
  duration?: number // seconds, mặc định 0.32 (response ~0.3-0.4 của Apple)
  bounce?: number // 0 = critically damped; 0.2 = flick/drawer
  velocity?: number // px/s hoặc đơn vị/s, kế thừa từ cử chỉ
  onFrame?: (value: number) => void
}

export interface SpringHandle {
  target: (value: number, options?: SpringOptions) => void
  stop: () => void
  value: () => number
}

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'

function prefersReducedMotion(): boolean {
  return typeof matchMedia !== 'undefined' && matchMedia(REDUCED_MOTION_QUERY).matches
}

/**
 * Một spring cho đúng một giá trị vô hướng. UI phức tạp hơn dùng nhiều spring song song
 * (X và Y tách riêng — không bao giờ một spring cho khoảng cách 2D).
 */
export function spring(initial: number, options: SpringOptions = {}): SpringHandle {
  let current = initial
  let velocity = options.velocity ?? 0
  let raf = 0
  let lastTime = 0
  let settled = true

  const damping = (bounce: number): number => 1 - Math.min(Math.max(bounce, 0), 1)

  function frame(time: number): void {
    const delta = lastTime === 0 ? 16 : Math.min(time - lastTime, 64)
    lastTime = time

    const omega = (2 * Math.PI) / ((options.duration ?? 0.32) * 1000)
    const zeta = damping(options.bounce ?? 0)

    // Tích phân nửa bước (semi-implicit Euler) đủ mượt cho UI, rẻ cho main thread.
    const accel = -omega * omega * (current - targetState.value) - 2 * zeta * omega * velocity
    velocity += (accel * delta) / 1000
    current += (velocity * delta) / 1000

    options.onFrame?.(current)

    if (Math.abs(velocity) < 0.01 && Math.abs(current - targetState.value) < 0.01) {
      current = targetState.value
      velocity = 0
      settled = true
      options.onFrame?.(current)
      return
    }

    raf = requestAnimationFrame(frame)
  }

  const targetState = { value: initial }

  function target(value: number, next: SpringOptions = {}): void {
    targetState.value = value
    velocity = next.velocity ?? velocity
    options = { ...options, ...next }
    if (settled) {
      settled = false
      lastTime = 0
      if (prefersReducedMotion()) {
        current = value
        velocity = 0
        settled = true
        options.onFrame?.(current)
        return
      }
      raf = requestAnimationFrame(frame)
    }
  }

  function stop(): void {
    if (raf) cancelAnimationFrame(raf)
    raf = 0
    settled = true
  }

  return { target, stop, value: () => current }
}

/** Biến dạng đượt theo biên — vật lý kẹo cao su (apple-design §9). */
export function rubberband(overshoot: number, dimension: number, constant = 0.55): number {
  return (overshoot * dimension * constant) / (dimension + constant * Math.abs(overshoot))
}

/** Chiếu điểm dừng theo quán tính, hàm exponential decay của Apple (không phải v²/2a). */
export function project(initialVelocity: number, decelerationRate = 0.998): number {
  return (initialVelocity / 1000) * decelerationRate * (1 / (1 - decelerationRate))
}

/** Lấy vận tốc từ lịch sử các sự kiện pointer (5 mẫu gần nhất). */
export function velocityTracker(): {
  push: (position: number, time: number) => void
  release: () => number
} {
  let history: Array<{ position: number; time: number }> = []

  return {
    push(position, time) {
      history.push({ position, time })
      if (history.length > 5) history.shift()
    },
    release() {
      if (history.length < 2) return 0
      const first = history[0]
      const last = history[history.length - 1]
      const dt = last.time - first.time
      if (dt === 0) return 0
      const v = ((last.position - first.position) / dt) * 1000
      history = []
      return v
    },
  }
}
