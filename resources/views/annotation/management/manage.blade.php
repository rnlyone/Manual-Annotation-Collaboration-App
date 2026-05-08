@php
	$packages = $contentdata['packages'] ?? $packages ?? collect();
	$sessions = $contentdata['sessions'] ?? $sessions ?? collect();
	$users = $contentdata['users'] ?? $users ?? collect();
	$packageSummaries = $contentdata['packageSummaries'] ?? $packageSummaries ?? collect();
	$totalItems       = $contentdata['totalItems'] ?? 0;
	$totalAnnotated   = $contentdata['totalAnnotated'] ?? 0;
	$totalUnannotated = $contentdata['totalUnannotated'] ?? 0;
	$overallProgress  = $contentdata['overallProgress'] ?? 0;
	$totalDAS         = $contentdata['totalDAS'] ?? 0;
	$totalNormal      = $contentdata['totalNormal'] ?? 0;
	$categoryDistrib  = $contentdata['categoryDistrib'] ?? collect();
@endphp

@push('styles')
<style>
	:root {
		--snippet-bg: #f8f9fa;
		--snippet-bg-hover: #eef2f7;
		--snippet-border: #e3e6ed;
	}

	[data-bs-theme="dark"],
	[data-theme="dark"],
	body.dark-style {
		--snippet-bg: #1f2633;
		--snippet-bg-hover: #2a3244;
		--snippet-border: #2f3748;
	}

	#annotationManageTable tbody tr td {
		vertical-align: top;
	}

	#annotationManageTable .data-snippet {
		max-width: 520px;
		white-space: pre-wrap;
		background-color: var(--snippet-bg);
		border: 1px solid var(--snippet-border);
		border-radius: 0.5rem;
		padding: 0.5rem 0.75rem;
		cursor: pointer;
		transition: background-color 0.2s ease, border-color 0.2s ease;
	}

	#annotationManageTable .data-snippet:hover {
		background-color: var(--snippet-bg-hover);
	}

	#annotationManageTable .data-snippet.collapsed {
		max-height: 140px;
		overflow-y: auto;
	}

	#annotationManageTable .data-snippet.expanded {
		max-height: none;
		overflow-y: visible;
	}

	.filter-controls .form-select,
	.filter-controls .btn {
		min-width: 160px;
	}

	.package-progress-table .progress {
		height: 6px;
	}

	.package-progress-table td {
		vertical-align: middle;
	}

	.package-progress-table .annotator-badge {
		font-size: 0.75rem;
	}
</style>
@endpush

<div class="container-xxl flex-grow-1 container-p-y">

	{{-- ─── Infographics ─── --}}
	<div class="row g-4 mb-4">

		{{-- Stat cards --}}
		<div class="col-12 col-xl-8">
			<div class="row g-3 h-100">
				<div class="col-6 col-md-3">
					<div class="card text-center h-100 py-2">
						<div class="card-body p-3 d-flex flex-column justify-content-center">
							<div class="fw-bold fs-3 text-primary">{{ number_format($totalItems) }}</div>
							<div class="text-muted small mt-1">Total Data Items</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-md-3">
					<div class="card text-center h-100 py-2">
						<div class="card-body p-3 d-flex flex-column justify-content-center">
							<div class="fw-bold fs-3 text-success">{{ number_format($totalAnnotated) }}</div>
							<div class="text-muted small mt-1">Annotated</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-md-3">
					<div class="card text-center h-100 py-2">
						<div class="card-body p-3 d-flex flex-column justify-content-center">
							<div class="fw-bold fs-3 text-warning">{{ number_format($totalUnannotated) }}</div>
							<div class="text-muted small mt-1">Unannotated</div>
						</div>
					</div>
				</div>
				<div class="col-6 col-md-3">
					<div class="card text-center h-100 py-2">
						<div class="card-body p-3 d-flex flex-column justify-content-center">
							<div class="fw-bold fs-3 text-info">{{ $overallProgress }}%</div>
							<div class="text-muted small mt-1">Overall Progress</div>
						</div>
					</div>
				</div>

				{{-- Overall progress bar card --}}
				<div class="col-12">
					<div class="card">
						<div class="card-body py-3 px-4">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="fw-semibold">Annotation Progress</span>
								<small class="text-muted">{{ number_format($totalAnnotated) }} / {{ number_format($totalItems) }}</small>
							</div>
							<div class="progress" style="height: 12px; border-radius: 6px;">
								<div class="progress-bar bg-success" style="width: {{ min($overallProgress, 100) }}%" title="{{ $overallProgress }}% annotated"></div>
							</div>
							<div class="d-flex justify-content-between mt-1">
								<small class="text-success">Normal: {{ number_format($totalNormal) }}</small>
								<small class="text-danger">DAS: {{ number_format($totalDAS) }}</small>
							</div>
						</div>
					</div>
				</div>

				{{-- Per-package mini bars --}}
				@if($packageSummaries->isNotEmpty())
				<div class="col-12">
					<div class="card">
						<div class="card-body py-3 px-4">
							<div class="fw-semibold mb-3">Per-Package Progress</div>
							<div class="d-flex flex-column gap-2">
								@foreach ($packageSummaries as $summary)
								<div>
									<div class="d-flex justify-content-between mb-1">
										<small class="text-truncate" style="max-width: 60%;" title="{{ $summary['name'] }}">{{ $summary['name'] }}</small>
										<small class="text-muted">{{ $summary['annotated_items'] }} / {{ $summary['total_items'] }} ({{ $summary['progress_percent'] }}%)</small>
									</div>
									<div class="progress" style="height: 7px; border-radius: 4px;">
										<div class="progress-bar" role="progressbar"
											style="width: {{ $summary['progress_percent'] }}%;"
											aria-valuenow="{{ $summary['progress_percent'] }}"
											aria-valuemin="0" aria-valuemax="100"></div>
									</div>
								</div>
								@endforeach
							</div>
						</div>
					</div>
				</div>
				@endif
			</div>
		</div>

		{{-- Donut chart: label distribution --}}
		<div class="col-12 col-xl-4">
			<div class="card h-100">
				<div class="card-body d-flex flex-column">
					<div class="fw-semibold mb-1">Label Distribution</div>
					<small class="text-muted mb-3">Distinct data items per category (DAS annotations)</small>
					<div class="flex-grow-1 d-flex align-items-center justify-content-center">
						<canvas id="labelDistribChart" style="max-height: 220px;"></canvas>
					</div>
					<div class="mt-3">
						@php
							$chartColors = ['#696cff', '#71dd37', '#ffab00', '#ff3e1d', '#03c3ec', '#8592a3'];
						@endphp
						<div class="d-flex flex-wrap gap-2 justify-content-center">
							<span class="badge" style="background: #8592a3;">Normal: {{ number_format($totalNormal) }}</span>
							@foreach ($categoryDistrib as $i => $cat)
								<span class="badge" style="background: {{ $chartColors[$i % count($chartColors)] }};">
									{{ $cat['name'] }}: {{ number_format($cat['count']) }}
								</span>
							@endforeach
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>
	{{-- ─── /Infographics ─── --}}

	<div class="row g-6">
		<div class="col-12">
			<div class="card">
				<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
					<div>
						<h5 class="mb-1">Annotation Management</h5>
						<small class="text-muted">Supervise annotated items by package or session, return batches for rework.</small>
					</div>
					<div class="filter-controls d-flex flex-wrap gap-2 align-items-center">
						<select class="form-select" id="scopeFilter">
							<option value="all">Whole data</option>
							<option value="package">Per package</option>
							<option value="session">Per session</option>
						</select>
						<select class="form-select" id="userFilter">
							<option value="">All annotators</option>
							@foreach ($users as $user)
								<option value="{{ $user->id }}">{{ $user->name }}</option>
							@endforeach
						</select>
						<select class="form-select d-none" id="packageFilter">
							<option value="">Select package</option>
							@foreach ($packages as $package)
								<option value="{{ $package->id }}">{{ $package->name }}</option>
							@endforeach
						</select>
						<select class="form-select d-none" id="sessionFilter">
							<option value="">Select session</option>
							@foreach ($sessions as $session)
								<option value="{{ $session['id'] }}">{{ $session['label'] }}</option>
							@endforeach
						</select>
						<button type="button" class="btn btn-outline-primary" id="refreshTable">Refresh</button>
					</div>
				</div>
				<div class="card-body">
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="selectAllRows">
							<label class="form-check-label" for="selectAllRows">Select all on this page</label>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" id="selectAllNoCategories">
							<label class="form-check-label" for="selectAllNoCategories">Select all without categories (all pages)</label>
						</div>
						<button type="button" class="btn btn-danger" id="returnSelectedBtn" disabled>
							Return for re-annotation (<span id="selectedCount">0</span>)
						</button>
					</div>
					<div class="table-responsive">
						<table class="table table-striped" id="annotationManageTable">
							<thead>
								<tr>
									<th style="width: 40px;"></th>
									<th style="width: 160px;">Assigned Packages</th>
									<th style="width: 160px;">Annotated At (Package)</th>
									<th style="width: 80px;">Annotator</th>
									<th style="width: 70px;">Categories</th>
									<th style="width: 120px;">Session</th>
									<th style="width: 400px;">Data</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@push('scripts')
<script>
	const tableRoute = "{{ route('annotations.manage.table') }}";
	const requeueRoute = "{{ route('annotations.manage.requeue') }}";
	const noCategoryIdsRoute = "{{ route('annotations.manage.noCategoryIds') }}";
	const csrfToken = '{{ csrf_token() }}';

	$(function () {
		const selectedAnnotations = new Set();
		const noCategoryState = { active: false, ids: new Set() };
		const SNIPPET_CHAR_LIMIT = 300;

		const escapeHtml = (value) => {
			return $('<div/>').text(value ?? '').html();
		};

		const truncateSnippet = (value) => {
			const text = value ?? '';
			return text.length > SNIPPET_CHAR_LIMIT ? `${text.slice(0, SNIPPET_CHAR_LIMIT)}...` : text;
		};

		const buildSnippetHtml = (value) => {
			const raw = value ?? '';
			const preview = truncateSnippet(raw);
			const encodedRaw = encodeURIComponent(raw);
			return `<div class="data-snippet collapsed" data-raw-text="${encodedRaw}" data-expanded="false" title="Click to expand or collapse">${escapeHtml(preview)}</div>`;
		};

		const buildPackageBadges = (packages) => {
			const items = Array.isArray(packages) ? packages : [];
			if (!items.length) {
				return '<span class="badge bg-label-secondary">Unassigned</span>';
			}

			return items
				.map(name => `<span class="badge bg-label-primary me-1 mb-1">${escapeHtml(name)}</span>`)
				.join('');
		};

		const buildAnnotatedPackageCell = (row) => {
			const name = row?.annotated_package_name;
			const timeline = row?.annotated_timestamp_human || row?.annotated_timestamp;
			if (!name && !timeline) {
				return '<span class="text-muted">—</span>';
			}

			let html = '<div class="d-flex flex-column">';
			html += `<span class="fw-semibold">${escapeHtml(name || '—')}</span>`;
			if (timeline) {
				html += `<small class="text-muted">${escapeHtml(timeline)}</small>`;
			}
			html += '</div>';
			return html;
		};

		const toggleSnippet = ($snippet) => {
			const rawAttr = $snippet.attr('data-raw-text') || '';
			let rawText = '';
			try {
				rawText = decodeURIComponent(rawAttr);
			} catch (error) {
				rawText = rawAttr;
			}

			const isExpanded = $snippet.attr('data-expanded') === 'true';
			if (isExpanded) {
				$snippet.attr('data-expanded', 'false');
				$snippet.removeClass('expanded').addClass('collapsed');
				$snippet.html(escapeHtml(truncateSnippet(rawText)));
			} else {
				$snippet.attr('data-expanded', 'true');
				$snippet.removeClass('collapsed').addClass('expanded');
				$snippet.html(escapeHtml(rawText));
			}
		};

		const table = $('#annotationManageTable').DataTable({
			processing: true,
			serverSide: true,
			ajax: {
				url: tableRoute,
				data: function (params) {
					params.scope = $('#scopeFilter').val();
					params.package_id = $('#packageFilter').val();
					params.session_id = $('#sessionFilter').val();
					params.user_id = $('#userFilter').val();
				}
			},
			order: [[2, 'desc']],
			columns: [
				{
					data: 'id',
					orderable: false,
					searchable: false,
					render: function (data) {
						return `<input type="checkbox" class="form-check-input row-checkbox" value="${data}">`;
					}
				},
				{
					data: 'assigned_packages',
					orderable: false,
					render: function (data) {
						return buildPackageBadges(data);
					}
				},
				{
					data: 'annotated_package_name',
					render: function (_data, _type, row) {
						return buildAnnotatedPackageCell(row);
					}
				},
				{ data: 'annotator' },
				{
					data: 'categories',
					orderable: false,
					render: function (data) {
						if (!data || !data.length) {
							return '<span class="badge bg-label-secondary">No categories</span>';
						}
						return data.map(name => `<span class="badge bg-label-info me-1 mb-1">${escapeHtml(name)}</span>`).join('');
					}
				},
				{
					data: 'session',
					render: function (data) {
						return data ? escapeHtml(data) : '—';
					}
				},
				{
					data: 'content',
					render: function (data) {
						return buildSnippetHtml(data);
					}
				}
			],
			drawCallback: function () {
				syncCheckboxStates();
			}
		});

		function syncCheckboxStates() {
			$('#annotationManageTable .row-checkbox').each(function () {
				const id = parseInt($(this).val(), 10);
				$(this).prop('checked', selectedAnnotations.has(id));
			});
			$('#selectAllRows').prop('checked', areAllRowsSelected());
			updateSelectedCounter();
			updateNoCategoryCheckboxState();
		}

		function areAllRowsSelected() {
			const checkboxes = $('#annotationManageTable .row-checkbox');
			if (!checkboxes.length) {
				return false;
			}
			return Array.from(checkboxes).every(cb => selectedAnnotations.has(parseInt(cb.value, 10)));
		}

		function updateSelectedCounter() {
			$('#selectedCount').text(selectedAnnotations.size);
			$('#returnSelectedBtn').prop('disabled', selectedAnnotations.size === 0);
		}

		function resetNoCategorySelection() {
			noCategoryState.active = false;
			noCategoryState.ids.clear();
			$('#selectAllNoCategories').prop('checked', false).prop('indeterminate', false);
		}

		function updateNoCategoryCheckboxState() {
			const $checkbox = $('#selectAllNoCategories');
			if (!noCategoryState.active || !noCategoryState.ids.size) {
				$checkbox.prop('checked', false).prop('indeterminate', false);
				return;
			}

			const allTrackedSelected = Array.from(noCategoryState.ids).every(id => selectedAnnotations.has(id));
			$checkbox.prop('checked', allTrackedSelected);
			$checkbox.prop('indeterminate', !allTrackedSelected);
		}

		function clearNoCategorySelection() {
			if (noCategoryState.ids.size) {
				noCategoryState.ids.forEach(id => selectedAnnotations.delete(id));
			}
			resetNoCategorySelection();
			syncCheckboxStates();
		}

		function selectAllWithoutCategories() {
			const $checkbox = $('#selectAllNoCategories');
			$checkbox.prop('disabled', true).prop('indeterminate', false);

			$.ajax({
				url: noCategoryIdsRoute,
				method: 'GET',
				data: {
					scope: $('#scopeFilter').val(),
					package_id: $('#packageFilter').val(),
					session_id: $('#sessionFilter').val(),
					user_id: $('#userFilter').val(),
				},
				success: function (response) {
					const ids = (response.ids || [])
						.map(id => parseInt(id, 10))
						.filter(id => Number.isFinite(id) && id > 0);

					noCategoryState.ids.clear();
					ids.forEach(id => {
						noCategoryState.ids.add(id);
						selectedAnnotations.add(id);
					});

					noCategoryState.active = ids.length > 0;

					if (!ids.length) {
						resetNoCategorySelection();
						Swal.fire('No Results', 'No annotations without categories found for the current filters.', 'info');
					}

					syncCheckboxStates();
				},
				error: function (xhr) {
					const message = xhr.responseJSON?.message || 'Unable to fetch annotations without categories.';
					resetNoCategorySelection();
					Swal.fire('Error', message, 'error');
				},
				complete: function () {
					$checkbox.prop('disabled', false);
				},
			});
		}

		$('#annotationManageTable').on('change', '.row-checkbox', function () {
			const id = parseInt($(this).val(), 10);
			if (this.checked) {
				selectedAnnotations.add(id);
			} else {
				selectedAnnotations.delete(id);
				if (noCategoryState.ids.has(id)) {
					noCategoryState.ids.delete(id);
					if (!noCategoryState.ids.size) {
						noCategoryState.active = false;
					}
				}
			}
			updateSelectedCounter();
			updateNoCategoryCheckboxState();
		});

		$('#selectAllRows').on('change', function () {
			const shouldSelect = this.checked;
			$('#annotationManageTable .row-checkbox').each(function () {
				const id = parseInt($(this).val(), 10);
				if (shouldSelect) {
					selectedAnnotations.add(id);
				} else {
					selectedAnnotations.delete(id);
				}
				$(this).prop('checked', shouldSelect);
			});
			updateSelectedCounter();
		});

		$('#selectAllNoCategories').on('change', function () {
			if (this.checked) {
				selectAllWithoutCategories();
			} else if (!this.indeterminate) {
				clearNoCategorySelection();
			}
		});

		$('#scopeFilter').on('change', function () {
			const scope = $(this).val();
			$('#packageFilter').toggleClass('d-none', scope !== 'package');
			$('#sessionFilter').toggleClass('d-none', scope !== 'session');
			selectedAnnotations.clear();
			resetNoCategorySelection();
			updateSelectedCounter();
			table.ajax.reload();
		});

		$('#packageFilter, #sessionFilter, #userFilter').on('change', function () {
			selectedAnnotations.clear();
			resetNoCategorySelection();
			updateSelectedCounter();
			table.ajax.reload();
		});

		$('#refreshTable').on('click', function () {
			selectedAnnotations.clear();
			resetNoCategorySelection();
			updateSelectedCounter();
			table.ajax.reload();
		});

		$('#returnSelectedBtn').on('click', function () {
			if (!selectedAnnotations.size) {
				return;
			}

			Swal.fire({
				title: 'Return selected annotations?',
				text: `This will delete ${selectedAnnotations.size} annotation(s) and notify the annotators.`,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Yes, return them',
				cancelButtonText: 'Cancel',
			}).then(result => {
				if (!result.isConfirmed) {
					return;
				}
				$.ajax({
					url: requeueRoute,
					method: 'POST',
					data: {
						_token: csrfToken,
						annotation_ids: Array.from(selectedAnnotations),
					},
					success: function (response) {
						Swal.fire('Done', response.message || 'Annotations returned.', 'success');
						selectedAnnotations.clear();
						resetNoCategorySelection();
						updateSelectedCounter();
						table.ajax.reload(null, false);
					},
					error: function (xhr) {
						const message = xhr.responseJSON?.message || 'Failed to return annotations.';
						Swal.fire('Error', message, 'error');
					}
				});
			});
		});

		$('#annotationManageTable').on('click', '.data-snippet', function () {
			toggleSnippet($(this));
		});
	});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
	(function () {
		const chartColors = ['#696cff','#71dd37','#ffab00','#ff3e1d','#03c3ec','#8592a3'];

		@php
			$chartLabels = $categoryDistrib->pluck('name')->toJson();
			$chartCounts = $categoryDistrib->pluck('count')->toJson();
			$chartColorsPhp = json_encode(array_slice(['#696cff','#71dd37','#ffab00','#ff3e1d','#03c3ec','#8592a3'], 0, $categoryDistrib->count()));
		@endphp

		const labels = {!! $chartLabels !!};
		const counts = {!! $chartCounts !!};
		const colors = {!! $chartColorsPhp !!};

		if (labels.length === 0) return;

		const ctx = document.getElementById('labelDistribChart');
		if (!ctx) return;

		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: counts,
					backgroundColor: colors,
					borderWidth: 2,
					borderColor: 'transparent',
					hoverOffset: 6,
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				cutout: '68%',
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
								const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
								return ` ${ctx.label}: ${ctx.parsed.toLocaleString()} (${pct}%)`;
							}
						}
					}
				}
			}
		});
	})();
</script>
@endpush
