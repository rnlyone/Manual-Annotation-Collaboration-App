@php
	$packages = $contentdata['packages'] ?? $packages ?? collect();
	$sessions = $contentdata['sessions'] ?? $sessions ?? collect();
	$users = $contentdata['users'] ?? $users ?? collect();
	$packageSummaries = $contentdata['packageSummaries'] ?? $packageSummaries ?? collect();
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
					@if ($packageSummaries->isNotEmpty())
						<div class="mb-4">
							<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
								<h6 class="mb-0">Package progress overview</h6>
								<small class="text-muted">Tracks total items, completed annotations, and current assignees.</small>
							</div>
							<div class="table-responsive">
								<table class="table table-sm table-hover align-middle package-progress-table mb-0">
									<thead>
										<tr>
											<th>Package</th>
											<th style="min-width: 260px;">Progress</th>
											<th>Annotators</th>
										</tr>
									</thead>
									<tbody>
										@foreach ($packageSummaries as $summary)
											<tr>
												<td>
													<div class="fw-semibold">{{ $summary['name'] }}</div>
													<small class="text-muted">{{ $summary['annotated_items'] }} / {{ $summary['total_items'] }} annotated</small>
												</td>
												<td>
													<div class="d-flex align-items-center gap-2">
														<div class="progress flex-grow-1">
															<div class="progress-bar" role="progressbar" style="width: {{ $summary['progress_percent'] }}%;" aria-valuenow="{{ $summary['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
														</div>
														<small class="text-muted">{{ $summary['progress_percent'] }}%</small>
													</div>
													@if ($summary['remaining'] > 0)
														<small class="text-muted">{{ $summary['remaining'] }} remaining</small>
													@endif
												</td>
												<td>
													@if (!empty($summary['annotators']))
														<div class="d-flex flex-wrap gap-1">
															@foreach ($summary['annotators'] as $annotator)
																<span class="badge bg-label-primary annotator-badge">{{ $annotator }}</span>
															@endforeach
														</div>
													@else
														<span class="text-muted">Unassigned</span>
													@endif
												</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					@endif
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
									<th style="width: 60px;">Package</th>
									<th style="width: 80px;">Annotator</th>
									<th style="width: 60px;">Categories</th>
									<th style="width: 100px;">Session</th>
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
			order: [[4, 'desc']],
			columns: [
				{
					data: 'id',
					orderable: false,
					searchable: false,
					render: function (data) {
						return `<input type="checkbox" class="form-check-input row-checkbox" value="${data}">`;
					}
				},
				{ data: 'package' },
				{ data: 'annotator' },
				{
					data: 'categories',
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
@endpush
