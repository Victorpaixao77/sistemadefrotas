package com.sistemafrotas.motorista.data.local;

import android.database.Cursor;
import android.os.CancellationSignal;
import androidx.annotation.NonNull;
import androidx.room.CoroutinesRoom;
import androidx.room.EntityInsertionAdapter;
import androidx.room.RoomDatabase;
import androidx.room.RoomSQLiteQuery;
import androidx.room.SharedSQLiteStatement;
import androidx.room.util.CursorUtil;
import androidx.room.util.DBUtil;
import androidx.sqlite.db.SupportSQLiteStatement;
import java.lang.Class;
import java.lang.Double;
import java.lang.Exception;
import java.lang.Float;
import java.lang.Integer;
import java.lang.Long;
import java.lang.Object;
import java.lang.Override;
import java.lang.String;
import java.lang.SuppressWarnings;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.concurrent.Callable;
import javax.annotation.processing.Generated;
import kotlin.Unit;
import kotlin.coroutines.Continuation;

@Generated("androidx.room.RoomProcessor")
@SuppressWarnings({"unchecked", "deprecation"})
public final class GpsPendingDao_Impl implements GpsPendingDao {
  private final RoomDatabase __db;

  private final EntityInsertionAdapter<GpsPendingEntity> __insertionAdapterOfGpsPendingEntity;

  private final SharedSQLiteStatement __preparedStmtOfDeleteById;

  public GpsPendingDao_Impl(@NonNull final RoomDatabase __db) {
    this.__db = __db;
    this.__insertionAdapterOfGpsPendingEntity = new EntityInsertionAdapter<GpsPendingEntity>(__db) {
      @Override
      @NonNull
      protected String createQuery() {
        return "INSERT OR ABORT INTO `gps_pending` (`id`,`veiculo_id`,`motorista_id`,`latitude`,`longitude`,`velocidade`,`bateria_pct`,`accuracy_metros`,`provider`,`location_mock`,`data_hora`,`created_at`) VALUES (nullif(?, 0),?,?,?,?,?,?,?,?,?,?,?)";
      }

      @Override
      protected void bind(@NonNull final SupportSQLiteStatement statement,
          @NonNull final GpsPendingEntity entity) {
        statement.bindLong(1, entity.getId());
        statement.bindLong(2, entity.getVeiculoId());
        statement.bindLong(3, entity.getMotoristaId());
        statement.bindDouble(4, entity.getLatitude());
        statement.bindDouble(5, entity.getLongitude());
        if (entity.getVelocidade() == null) {
          statement.bindNull(6);
        } else {
          statement.bindDouble(6, entity.getVelocidade());
        }
        if (entity.getBateriaPct() == null) {
          statement.bindNull(7);
        } else {
          statement.bindLong(7, entity.getBateriaPct());
        }
        if (entity.getAccuracyMetros() == null) {
          statement.bindNull(8);
        } else {
          statement.bindDouble(8, entity.getAccuracyMetros());
        }
        if (entity.getProvider() == null) {
          statement.bindNull(9);
        } else {
          statement.bindString(9, entity.getProvider());
        }
        if (entity.getLocationMock() == null) {
          statement.bindNull(10);
        } else {
          statement.bindLong(10, entity.getLocationMock());
        }
        statement.bindString(11, entity.getDataHora());
        statement.bindLong(12, entity.getCreatedAt());
      }
    };
    this.__preparedStmtOfDeleteById = new SharedSQLiteStatement(__db) {
      @Override
      @NonNull
      public String createQuery() {
        final String _query = "DELETE FROM gps_pending WHERE id = ?";
        return _query;
      }
    };
  }

  @Override
  public Object insert(final GpsPendingEntity entity,
      final Continuation<? super Long> $completion) {
    return CoroutinesRoom.execute(__db, true, new Callable<Long>() {
      @Override
      @NonNull
      public Long call() throws Exception {
        __db.beginTransaction();
        try {
          final Long _result = __insertionAdapterOfGpsPendingEntity.insertAndReturnId(entity);
          __db.setTransactionSuccessful();
          return _result;
        } finally {
          __db.endTransaction();
        }
      }
    }, $completion);
  }

  @Override
  public Object deleteById(final long id, final Continuation<? super Unit> $completion) {
    return CoroutinesRoom.execute(__db, true, new Callable<Unit>() {
      @Override
      @NonNull
      public Unit call() throws Exception {
        final SupportSQLiteStatement _stmt = __preparedStmtOfDeleteById.acquire();
        int _argIndex = 1;
        _stmt.bindLong(_argIndex, id);
        try {
          __db.beginTransaction();
          try {
            _stmt.executeUpdateDelete();
            __db.setTransactionSuccessful();
            return Unit.INSTANCE;
          } finally {
            __db.endTransaction();
          }
        } finally {
          __preparedStmtOfDeleteById.release(_stmt);
        }
      }
    }, $completion);
  }

  @Override
  public Object listOldest(final int limit,
      final Continuation<? super List<GpsPendingEntity>> $completion) {
    final String _sql = "SELECT * FROM gps_pending ORDER BY created_at ASC LIMIT ?";
    final RoomSQLiteQuery _statement = RoomSQLiteQuery.acquire(_sql, 1);
    int _argIndex = 1;
    _statement.bindLong(_argIndex, limit);
    final CancellationSignal _cancellationSignal = DBUtil.createCancellationSignal();
    return CoroutinesRoom.execute(__db, false, _cancellationSignal, new Callable<List<GpsPendingEntity>>() {
      @Override
      @NonNull
      public List<GpsPendingEntity> call() throws Exception {
        final Cursor _cursor = DBUtil.query(__db, _statement, false, null);
        try {
          final int _cursorIndexOfId = CursorUtil.getColumnIndexOrThrow(_cursor, "id");
          final int _cursorIndexOfVeiculoId = CursorUtil.getColumnIndexOrThrow(_cursor, "veiculo_id");
          final int _cursorIndexOfMotoristaId = CursorUtil.getColumnIndexOrThrow(_cursor, "motorista_id");
          final int _cursorIndexOfLatitude = CursorUtil.getColumnIndexOrThrow(_cursor, "latitude");
          final int _cursorIndexOfLongitude = CursorUtil.getColumnIndexOrThrow(_cursor, "longitude");
          final int _cursorIndexOfVelocidade = CursorUtil.getColumnIndexOrThrow(_cursor, "velocidade");
          final int _cursorIndexOfBateriaPct = CursorUtil.getColumnIndexOrThrow(_cursor, "bateria_pct");
          final int _cursorIndexOfAccuracyMetros = CursorUtil.getColumnIndexOrThrow(_cursor, "accuracy_metros");
          final int _cursorIndexOfProvider = CursorUtil.getColumnIndexOrThrow(_cursor, "provider");
          final int _cursorIndexOfLocationMock = CursorUtil.getColumnIndexOrThrow(_cursor, "location_mock");
          final int _cursorIndexOfDataHora = CursorUtil.getColumnIndexOrThrow(_cursor, "data_hora");
          final int _cursorIndexOfCreatedAt = CursorUtil.getColumnIndexOrThrow(_cursor, "created_at");
          final List<GpsPendingEntity> _result = new ArrayList<GpsPendingEntity>(_cursor.getCount());
          while (_cursor.moveToNext()) {
            final GpsPendingEntity _item;
            final long _tmpId;
            _tmpId = _cursor.getLong(_cursorIndexOfId);
            final int _tmpVeiculoId;
            _tmpVeiculoId = _cursor.getInt(_cursorIndexOfVeiculoId);
            final int _tmpMotoristaId;
            _tmpMotoristaId = _cursor.getInt(_cursorIndexOfMotoristaId);
            final double _tmpLatitude;
            _tmpLatitude = _cursor.getDouble(_cursorIndexOfLatitude);
            final double _tmpLongitude;
            _tmpLongitude = _cursor.getDouble(_cursorIndexOfLongitude);
            final Double _tmpVelocidade;
            if (_cursor.isNull(_cursorIndexOfVelocidade)) {
              _tmpVelocidade = null;
            } else {
              _tmpVelocidade = _cursor.getDouble(_cursorIndexOfVelocidade);
            }
            final Integer _tmpBateriaPct;
            if (_cursor.isNull(_cursorIndexOfBateriaPct)) {
              _tmpBateriaPct = null;
            } else {
              _tmpBateriaPct = _cursor.getInt(_cursorIndexOfBateriaPct);
            }
            final Float _tmpAccuracyMetros;
            if (_cursor.isNull(_cursorIndexOfAccuracyMetros)) {
              _tmpAccuracyMetros = null;
            } else {
              _tmpAccuracyMetros = _cursor.getFloat(_cursorIndexOfAccuracyMetros);
            }
            final String _tmpProvider;
            if (_cursor.isNull(_cursorIndexOfProvider)) {
              _tmpProvider = null;
            } else {
              _tmpProvider = _cursor.getString(_cursorIndexOfProvider);
            }
            final Integer _tmpLocationMock;
            if (_cursor.isNull(_cursorIndexOfLocationMock)) {
              _tmpLocationMock = null;
            } else {
              _tmpLocationMock = _cursor.getInt(_cursorIndexOfLocationMock);
            }
            final String _tmpDataHora;
            _tmpDataHora = _cursor.getString(_cursorIndexOfDataHora);
            final long _tmpCreatedAt;
            _tmpCreatedAt = _cursor.getLong(_cursorIndexOfCreatedAt);
            _item = new GpsPendingEntity(_tmpId,_tmpVeiculoId,_tmpMotoristaId,_tmpLatitude,_tmpLongitude,_tmpVelocidade,_tmpBateriaPct,_tmpAccuracyMetros,_tmpProvider,_tmpLocationMock,_tmpDataHora,_tmpCreatedAt);
            _result.add(_item);
          }
          return _result;
        } finally {
          _cursor.close();
          _statement.release();
        }
      }
    }, $completion);
  }

  @Override
  public Object count(final Continuation<? super Integer> $completion) {
    final String _sql = "SELECT COUNT(*) FROM gps_pending";
    final RoomSQLiteQuery _statement = RoomSQLiteQuery.acquire(_sql, 0);
    final CancellationSignal _cancellationSignal = DBUtil.createCancellationSignal();
    return CoroutinesRoom.execute(__db, false, _cancellationSignal, new Callable<Integer>() {
      @Override
      @NonNull
      public Integer call() throws Exception {
        final Cursor _cursor = DBUtil.query(__db, _statement, false, null);
        try {
          final Integer _result;
          if (_cursor.moveToFirst()) {
            final int _tmp;
            _tmp = _cursor.getInt(0);
            _result = _tmp;
          } else {
            _result = 0;
          }
          return _result;
        } finally {
          _cursor.close();
          _statement.release();
        }
      }
    }, $completion);
  }

  @NonNull
  public static List<Class<?>> getRequiredConverters() {
    return Collections.emptyList();
  }
}
