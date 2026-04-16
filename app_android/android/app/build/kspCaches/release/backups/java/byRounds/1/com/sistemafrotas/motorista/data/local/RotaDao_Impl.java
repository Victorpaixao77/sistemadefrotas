package com.sistemafrotas.motorista.data.local;

import android.database.Cursor;
import androidx.annotation.NonNull;
import androidx.room.EntityInsertionAdapter;
import androidx.room.RoomDatabase;
import androidx.room.RoomSQLiteQuery;
import androidx.room.SharedSQLiteStatement;
import androidx.room.util.CursorUtil;
import androidx.room.util.DBUtil;
import androidx.sqlite.db.SupportSQLiteStatement;
import java.lang.Class;
import java.lang.Double;
import java.lang.Integer;
import java.lang.Override;
import java.lang.String;
import java.lang.SuppressWarnings;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import javax.annotation.processing.Generated;

@Generated("androidx.room.RoomProcessor")
@SuppressWarnings({"unchecked", "deprecation"})
public final class RotaDao_Impl implements RotaDao {
  private final RoomDatabase __db;

  private final EntityInsertionAdapter<RotaEntity> __insertionAdapterOfRotaEntity;

  private final SharedSQLiteStatement __preparedStmtOfDeleteAll;

  public RotaDao_Impl(@NonNull final RoomDatabase __db) {
    this.__db = __db;
    this.__insertionAdapterOfRotaEntity = new EntityInsertionAdapter<RotaEntity>(__db) {
      @Override
      @NonNull
      protected String createQuery() {
        return "INSERT OR REPLACE INTO `rotas_cache` (`id`,`veiculoId`,`dataRota`,`dataSaida`,`cidadeOrigemNome`,`cidadeDestinoNome`,`placa`,`status`,`distanciaKm`,`syncedAt`) VALUES (?,?,?,?,?,?,?,?,?,?)";
      }

      @Override
      protected void bind(@NonNull final SupportSQLiteStatement statement,
          @NonNull final RotaEntity entity) {
        statement.bindLong(1, entity.getId());
        if (entity.getVeiculoId() == null) {
          statement.bindNull(2);
        } else {
          statement.bindLong(2, entity.getVeiculoId());
        }
        if (entity.getDataRota() == null) {
          statement.bindNull(3);
        } else {
          statement.bindString(3, entity.getDataRota());
        }
        if (entity.getDataSaida() == null) {
          statement.bindNull(4);
        } else {
          statement.bindString(4, entity.getDataSaida());
        }
        if (entity.getCidadeOrigemNome() == null) {
          statement.bindNull(5);
        } else {
          statement.bindString(5, entity.getCidadeOrigemNome());
        }
        if (entity.getCidadeDestinoNome() == null) {
          statement.bindNull(6);
        } else {
          statement.bindString(6, entity.getCidadeDestinoNome());
        }
        if (entity.getPlaca() == null) {
          statement.bindNull(7);
        } else {
          statement.bindString(7, entity.getPlaca());
        }
        if (entity.getStatus() == null) {
          statement.bindNull(8);
        } else {
          statement.bindString(8, entity.getStatus());
        }
        if (entity.getDistanciaKm() == null) {
          statement.bindNull(9);
        } else {
          statement.bindDouble(9, entity.getDistanciaKm());
        }
        statement.bindLong(10, entity.getSyncedAt());
      }
    };
    this.__preparedStmtOfDeleteAll = new SharedSQLiteStatement(__db) {
      @Override
      @NonNull
      public String createQuery() {
        final String _query = "DELETE FROM rotas_cache";
        return _query;
      }
    };
  }

  @Override
  public void insertAll(final List<RotaEntity> rotas) {
    __db.assertNotSuspendingTransaction();
    __db.beginTransaction();
    try {
      __insertionAdapterOfRotaEntity.insert(rotas);
      __db.setTransactionSuccessful();
    } finally {
      __db.endTransaction();
    }
  }

  @Override
  public void deleteAll() {
    __db.assertNotSuspendingTransaction();
    final SupportSQLiteStatement _stmt = __preparedStmtOfDeleteAll.acquire();
    try {
      __db.beginTransaction();
      try {
        _stmt.executeUpdateDelete();
        __db.setTransactionSuccessful();
      } finally {
        __db.endTransaction();
      }
    } finally {
      __preparedStmtOfDeleteAll.release(_stmt);
    }
  }

  @Override
  public List<RotaEntity> getAll() {
    final String _sql = "SELECT * FROM rotas_cache ORDER BY dataRota DESC, id DESC";
    final RoomSQLiteQuery _statement = RoomSQLiteQuery.acquire(_sql, 0);
    __db.assertNotSuspendingTransaction();
    final Cursor _cursor = DBUtil.query(__db, _statement, false, null);
    try {
      final int _cursorIndexOfId = CursorUtil.getColumnIndexOrThrow(_cursor, "id");
      final int _cursorIndexOfVeiculoId = CursorUtil.getColumnIndexOrThrow(_cursor, "veiculoId");
      final int _cursorIndexOfDataRota = CursorUtil.getColumnIndexOrThrow(_cursor, "dataRota");
      final int _cursorIndexOfDataSaida = CursorUtil.getColumnIndexOrThrow(_cursor, "dataSaida");
      final int _cursorIndexOfCidadeOrigemNome = CursorUtil.getColumnIndexOrThrow(_cursor, "cidadeOrigemNome");
      final int _cursorIndexOfCidadeDestinoNome = CursorUtil.getColumnIndexOrThrow(_cursor, "cidadeDestinoNome");
      final int _cursorIndexOfPlaca = CursorUtil.getColumnIndexOrThrow(_cursor, "placa");
      final int _cursorIndexOfStatus = CursorUtil.getColumnIndexOrThrow(_cursor, "status");
      final int _cursorIndexOfDistanciaKm = CursorUtil.getColumnIndexOrThrow(_cursor, "distanciaKm");
      final int _cursorIndexOfSyncedAt = CursorUtil.getColumnIndexOrThrow(_cursor, "syncedAt");
      final List<RotaEntity> _result = new ArrayList<RotaEntity>(_cursor.getCount());
      while (_cursor.moveToNext()) {
        final RotaEntity _item;
        final int _tmpId;
        _tmpId = _cursor.getInt(_cursorIndexOfId);
        final Integer _tmpVeiculoId;
        if (_cursor.isNull(_cursorIndexOfVeiculoId)) {
          _tmpVeiculoId = null;
        } else {
          _tmpVeiculoId = _cursor.getInt(_cursorIndexOfVeiculoId);
        }
        final String _tmpDataRota;
        if (_cursor.isNull(_cursorIndexOfDataRota)) {
          _tmpDataRota = null;
        } else {
          _tmpDataRota = _cursor.getString(_cursorIndexOfDataRota);
        }
        final String _tmpDataSaida;
        if (_cursor.isNull(_cursorIndexOfDataSaida)) {
          _tmpDataSaida = null;
        } else {
          _tmpDataSaida = _cursor.getString(_cursorIndexOfDataSaida);
        }
        final String _tmpCidadeOrigemNome;
        if (_cursor.isNull(_cursorIndexOfCidadeOrigemNome)) {
          _tmpCidadeOrigemNome = null;
        } else {
          _tmpCidadeOrigemNome = _cursor.getString(_cursorIndexOfCidadeOrigemNome);
        }
        final String _tmpCidadeDestinoNome;
        if (_cursor.isNull(_cursorIndexOfCidadeDestinoNome)) {
          _tmpCidadeDestinoNome = null;
        } else {
          _tmpCidadeDestinoNome = _cursor.getString(_cursorIndexOfCidadeDestinoNome);
        }
        final String _tmpPlaca;
        if (_cursor.isNull(_cursorIndexOfPlaca)) {
          _tmpPlaca = null;
        } else {
          _tmpPlaca = _cursor.getString(_cursorIndexOfPlaca);
        }
        final String _tmpStatus;
        if (_cursor.isNull(_cursorIndexOfStatus)) {
          _tmpStatus = null;
        } else {
          _tmpStatus = _cursor.getString(_cursorIndexOfStatus);
        }
        final Double _tmpDistanciaKm;
        if (_cursor.isNull(_cursorIndexOfDistanciaKm)) {
          _tmpDistanciaKm = null;
        } else {
          _tmpDistanciaKm = _cursor.getDouble(_cursorIndexOfDistanciaKm);
        }
        final long _tmpSyncedAt;
        _tmpSyncedAt = _cursor.getLong(_cursorIndexOfSyncedAt);
        _item = new RotaEntity(_tmpId,_tmpVeiculoId,_tmpDataRota,_tmpDataSaida,_tmpCidadeOrigemNome,_tmpCidadeDestinoNome,_tmpPlaca,_tmpStatus,_tmpDistanciaKm,_tmpSyncedAt);
        _result.add(_item);
      }
      return _result;
    } finally {
      _cursor.close();
      _statement.release();
    }
  }

  @NonNull
  public static List<Class<?>> getRequiredConverters() {
    return Collections.emptyList();
  }
}
