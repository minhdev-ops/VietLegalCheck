@extends('layouts.pdf')

@section('content')
  <div class="cover">
    <h1>Báo cáo đánh giá nghĩa vụ pháp lý</h1>
    <p class="subtitle">{{ $assessment->project->name ?? '' }}</p>
    <table class="meta">
      <tr><td>Ngày đánh giá pháp lý</td><td>{{ $as_of_date }}</td></tr>
      <tr><td>Ngày tạo báo cáo</td><td>{{ $generated_at }}</td></tr>
      <tr><td>Mã đánh giá</td><td>{{ $assessment->id }}</td></tr>
      <tr><td>Phiên bản bộ quy tắc</td><td>{{ collect($rule_set_snapshot)->map(fn($r) => $r['rule_code'].' v'.$r['version'])->implode(', ') }}</td></tr>
      <tr><td>Phiên bản bộ câu hỏi</td><td>v{{ $question_set_version }}</td></tr>
    </table>
  </div>

  <div class="note">
    Kết quả đánh giá hỗ trợ dựa trên dữ liệu khai báo, thông tin công khai và bộ quy tắc pháp lý
    tại thời điểm thực hiện. Đây không phải giấy phép, chứng nhận của cơ quan nhà nước và không
    thay thế ý kiến tư vấn pháp lý chuyên nghiệp (SRS FR-REPORT-05).
  </div>

  <h2>Mục lục</h2>
  <ul class="toc">
    @foreach($findings->groupBy('legal_group') as $group => $items)
      <li>{{ $group }} ({{ $items->count() }})</li>
    @endforeach
  </ul>

  @foreach($findings->groupBy('legal_group') as $group => $items)
    <h2>{{ $group }}</h2>
    @foreach($items as $finding)
      <div class="finding">
        <h3>{{ $finding->title }}</h3>
        <p class="chip">{{ $finding->applicability->value }} · {{ $finding->severity->value }}</p>
        <p>{{ $finding->explanation }}</p>
        @if($finding->recommendation)
          <p><strong>Khuyến nghị:</strong> {{ $finding->recommendation }}</p>
        @endif
        @if($finding->legal_basis)
          <table class="basis">
            <thead><tr><th>Điều khoản</th><th>Văn bản</th></tr></thead>
            <tbody>
              @foreach($finding->legal_basis as $basis)
                <tr><td>{{ $basis['citation'] }}</td><td>{{ $basis['document_number'] }}</td></tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    @endforeach
  @endforeach

  <p class="hash">Mã băm SHA-256 của bộ quy tắc đã dùng: {{ $rule_set_hash ?? '' }}<br>Mã băm file PDF được ghi kèm báo cáo khi tải về.</p>
@endsection
