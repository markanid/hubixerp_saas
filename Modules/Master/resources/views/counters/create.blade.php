@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item">Dashboard</li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cash-register"></i> {{ $page_title }}</h3>
                    <a class="btn btn-dark btn-sm btn-flat float-right" href="{{ route('counters.index') }}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
                </div>
                <form method="post" action="{{ route('counters.update') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ old('id', $counter->id) }}">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Counter Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $counter->name) }}" required>
                            @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label>User</label>
                            <select name="user_id" class="form-control">
                                <option value="">Any user</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ (string) old('user_id', $counter->user_id) === (string) $user->id ? 'selected' : '' }}>
                                        {{ $user->user_name }} - {{ $user->user_role }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="isActive" {{ old('is_active', $counter->is_active) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="isActive">Active</label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-flat"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
