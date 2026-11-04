<script setup lang="ts">
/**
 * Tuyên bố giới hạn trách nhiệm (SRS FR-REPORT-05 + 9.3): hiện ở đầu trang kết quả
 * và trong báo cáo. Nội dung là văn bản cố định do nghiệp vụ quy định, không dịch theo ngữ cảnh.
 */
const props = withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })
</script>

<template>
  <div
    :class="[
      'rounded-md border border-line bg-surface-2 text-[12.5px] text-ink-muted',
      props.compact ? 'px-3 py-2' : 'px-4 py-3',
    ]"
    role="note"
  >
    Kết quả đánh giá hỗ trợ dựa trên dữ liệu bạn khai báo, thông tin công khai và bộ quy tắc pháp lý
    tại thời điểm thực hiện. Đây <strong class="font-semibold text-ink">không phải</strong> giấy phép,
    chứng nhận của cơ quan nhà nước và không thay thế ý kiến tư vấn pháp lý chuyên nghiệp.
  </div>
</template>
